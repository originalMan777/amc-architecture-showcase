<?php

namespace App\SiteIntake\Validation;

use App\SiteIntake\Data\SiteIntakePackage;
use App\SiteIntake\Data\ValidationResult;

final class SiteIntakeValidator
{
    private array $errors = [];

    public function validate(SiteIntakePackage $package): ValidationResult
    {
        $this->errors = [];
        if ($package->malformed) {
            return new ValidationResult('malformed_package', [$this->error('$', 'malformed_package', 'Package documents could not be decoded.')]);
        }

        $m = $package->manifest;
        $p = $package->profile;
        $version = $m['contract_version'] ?? $p['version'] ?? null;
        if (is_string($version) && version_compare($version, '1.0.0', '>')) {
            return new ValidationResult('unsupported_future_version', [$this->error('contract_version', 'unsupported_future_version', 'Contract version is newer than supported.')]);
        }
        if (is_string($version) && version_compare($version, '1.0.0', '<')) {
            return new ValidationResult('unsupported_old_version', [$this->error('contract_version', 'unsupported_old_version', 'Contract version is older than supported.')]);
        }

        foreach (['contract', 'contract_version', 'package_id', 'export_id', 'source_system', 'source_environment', 'source_project_id', 'source_client_id', 'created_at', 'profile_filename', 'total_file_count', 'total_uncompressed_bytes'] as $key) {
            $this->required($m, $key, "manifest.$key");
        }
        $this->required($p, 'contract', 'profile.contract');
        $this->required($p, 'version', 'profile.version');
        $this->required($p, 'identity', 'profile.identity');
        $this->required($p, 'project', 'profile.project');
        if (($m['contract'] ?? null) !== 'amc-site-intake' || ($p['contract'] ?? null) !== 'amc-site-intake') {
            $this->add('contract', 'invalid_contract', 'Contract name must be amc-site-intake.');
        }
        $this->required($p['identity'] ?? [], 'business_name', 'profile.identity.business_name');
        $tier = $p['project']['selected_tier'] ?? null;
        if (! in_array($tier, config('site_intake.tiers'), true)) {
            $this->add('profile.project.selected_tier', 'unsupported_tier', 'Selected tier is not supported.');
        }
        foreach (($p['modules']['selected'] ?? []) as $i => $module) {
            if (! in_array($module, config('site_intake.modules'), true)) {
                $this->add("profile.modules.selected.$i", 'unsupported_module', 'Selected module is not supported.');
            }
        }
        $encoded = json_encode([$m, $p], JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && strlen($encoded) > config('site_intake.limits.max_metadata_bytes')) {
            $this->add('$', 'metadata_too_large', 'Package metadata exceeds the configured limit.');
        }
        if ($this->containsSecretKey([$m, $p])) {
            $this->add('$', 'secret_material', 'Secret-bearing fields are forbidden.');
        }

        $declared = $m['files'] ?? [];
        if (! is_array($declared)) {
            $this->add('manifest.files', 'invalid_type', 'Files must be an array.');
        } else {
            $this->files($declared, $package->observedFiles, $m);
        }
        foreach (($p['review']['normalization_warnings'] ?? []) as $i => $warning) {
            if (is_string($warning) && $warning !== '') {
                $this->add("profile.review.normalization_warnings.$i", 'normalization_warning', $warning, 'warning');
            }
        }
        $hasError = collect($this->errors)->contains(fn ($e) => $e['severity'] === 'error');

        return new ValidationResult($hasError ? 'invalid' : ($this->errors ? 'valid_with_warnings' : 'valid'), $this->errors);
    }

    private function files(array $files, array $observed, array $manifest): void
    {
        $limits = config('site_intake.limits');
        $paths = [];
        $total = 0;
        if (count($files) > $limits['max_files']) {
            $this->add('manifest.files', 'too_many_files', 'Declared file count exceeds the configured limit.');
        }
        foreach ($files as $i => $file) {
            if (! is_array($file)) {
                $this->add("manifest.files.$i", 'invalid_type', 'File entry must be an object.');

                continue;
            }
            $path = $file['path'] ?? '';
            $size = $file['bytes'] ?? -1;
            $mime = strtolower((string) ($file['mime_type'] ?? ''));
            if (! is_string($path) || $path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) || str_contains($path, '\\')) {
                $this->add("manifest.files.$i.path", 'unsafe_path', 'File path must be a safe relative POSIX path.');
            }
            if (isset($paths[strtolower((string) $path)])) {
                $this->add("manifest.files.$i.path", 'duplicate_path', 'Destination paths must be unique.');
            }
            $paths[strtolower((string) $path)] = true;
            $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            if (! preg_match('/^[a-f0-9]{64}$/', (string) ($file['sha256'] ?? ''))) {
                $this->add("manifest.files.$i.sha256", 'invalid_checksum', 'SHA-256 must be 64 lowercase hexadecimal characters.');
            }
            if (str_starts_with((string) $path, 'assets/')) {
                if (! isset(config('site_intake.asset_mimes')[$ext]) || ! in_array($mime, config('site_intake.asset_mimes')[$ext] ?? [], true)) {
                    $this->add("manifest.files.$i.mime_type", 'disallowed_asset_type', 'Extension and MIME type must match the allowlist.');
                }
                if (($file['entry_type'] ?? 'file') !== 'file') {
                    $this->add("manifest.files.$i.entry_type", 'non_regular_file', 'Symlinks and non-regular files are forbidden.');
                }
                $url = $file['asset']['external_url'] ?? null;
                if ($url !== null && (! is_string($url) || ! str_starts_with(strtolower($url), 'https://') || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
                    $this->add("manifest.files.$i.asset.external_url", 'unsafe_external_url', 'External asset URLs must use HTTPS and contain no credentials.');
                }
            }
            if (! is_int($size) || $size < 0) {
                $this->add("manifest.files.$i.bytes", 'invalid_size', 'Byte size must be a non-negative integer.');
            } elseif ($size > $limits['max_file_bytes']) {
                $this->add("manifest.files.$i.bytes", 'file_too_large', 'File exceeds the configured individual limit.');
            }
            $total += max(0, is_int($size) ? $size : 0);
            if (isset($observed[$path]) && ($observed[$path]['sha256'] ?? null) !== ($file['sha256'] ?? null)) {
                $this->add("manifest.files.$i.sha256", 'checksum_mismatch', 'Observed checksum does not match the manifest.');
            }
        }
        if ($total > $limits['max_total_bytes']) {
            $this->add('manifest.total_uncompressed_bytes', 'package_too_large', 'Package exceeds the total uncompressed size limit.');
        }
        if (($manifest['total_file_count'] ?? null) !== count($files)) {
            $this->add('manifest.total_file_count', 'file_count_mismatch', 'Declared file count does not match files.');
        }
        if (($manifest['total_uncompressed_bytes'] ?? null) !== $total) {
            $this->add('manifest.total_uncompressed_bytes', 'size_mismatch', 'Declared total size does not match files.');
        }
    }

    private function containsSecretKey(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $key => $child) {
            if (is_string($key) && preg_match('/(^|_)(password|passwd|secret|token|api_key|private_key|payment_credential)s?$/i', $key)) {
                return true;
            }
            if ($this->containsSecretKey($child)) {
                return true;
            }
        }

        return false;
    }

    private function required(array $data, string $key, string $path): void
    {
        if (! array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
            $this->add($path, 'required', 'Required value is missing.');
        }
    }

    private function add(string $path, string $code, string $message, string $severity = 'error'): void
    {
        $this->errors[] = $this->error($path, $code, $message, $severity);
    }

    private function error(string $path, string $code, string $message, string $severity = 'error'): array
    {
        return compact('path', 'code', 'message', 'severity');
    }
}
