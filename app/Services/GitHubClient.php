<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Http;
use App\Core\Logger;
use App\Core\Settings;
use RuntimeException;

/**
 * Minimal GitHub REST client used by the auto-update system.
 * The personal access token is stored encrypted in the settings table and
 * is never written to a log or rendered in the UI.
 */
final class GitHubClient
{
    private const API = 'https://api.github.com';

    private string $repository;

    private string $branch;

    private string $token;

    public function __construct(?string $repository = null, ?string $branch = null, ?string $token = null)
    {
        $this->repository = trim($repository ?? (string) (Settings::get('github_repo') ?: ''), '/ ');
        $this->branch = trim($branch ?? (string) (Settings::get('github_branch') ?: 'main'));
        $this->token = $token ?? (string) (Settings::get('github_token') ?: '');

        // Accept a full URL as well as owner/repo.
        if (preg_match('#github\.com[:/]([^/]+/[^/.]+)#i', $this->repository, $matches) === 1) {
            $this->repository = $matches[1];
        }
        $this->repository = preg_replace('/\.git$/', '', $this->repository) ?? $this->repository;
    }

    public function isConfigured(): bool
    {
        return $this->repository !== '' && preg_match('#^[\w.\-]+/[\w.\-]+$#', $this->repository) === 1;
    }

    public function repository(): string
    {
        return $this->repository;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * Credentials/reachability test for the admin screen.
     *
     * @return array{success:bool,message:string,private:bool,default_branch:string}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Enter the repository as owner/repository.', 'private' => false, 'default_branch' => ''];
        }

        $response = Http::get(self::API . '/repos/' . $this->repository, $this->headers(), 20);

        return match (true) {
            $response['status'] === 200 && is_array($response['json']) => [
                'success'        => true,
                'message'        => 'Connected to ' . $this->repository . ' (' . ((bool) $response['json']['private'] ? 'private' : 'public') . ').',
                'private'        => (bool) $response['json']['private'],
                'default_branch' => (string) ($response['json']['default_branch'] ?? 'main'),
            ],
            $response['status'] === 404 => [
                'success' => false,
                'message' => 'Repository not found. Check the name, and add a personal access token if it is private.',
                'private' => false, 'default_branch' => '',
            ],
            $response['status'] === 401 || $response['status'] === 403 => [
                'success' => false,
                'message' => 'GitHub rejected the token (HTTP ' . $response['status'] . '). Make sure it has "Contents: read" access.',
                'private' => false, 'default_branch' => '',
            ],
            default => [
                'success' => false,
                'message' => 'Could not reach GitHub (HTTP ' . $response['status'] . ') ' . $response['error'],
                'private' => false, 'default_branch' => '',
            ],
        };
    }

    /**
     * Latest commit on the configured branch.
     *
     * @return array{sha:string,short:string,message:string,author:string,date:string,url:string}
     */
    public function latestCommit(): array
    {
        $this->assertConfigured();

        $response = Http::get(
            self::API . '/repos/' . $this->repository . '/commits/' . rawurlencode($this->branch),
            $this->headers(),
            25
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            throw new RuntimeException($this->describeError($response, 'Could not read the latest commit'));
        }

        $commit = $response['json'];

        return [
            'sha'     => (string) ($commit['sha'] ?? ''),
            'short'   => substr((string) ($commit['sha'] ?? ''), 0, 8),
            'message' => (string) ($commit['commit']['message'] ?? ''),
            'author'  => (string) ($commit['commit']['author']['name'] ?? 'unknown'),
            'date'    => (string) ($commit['commit']['author']['date'] ?? ''),
            'url'     => (string) ($commit['html_url'] ?? ''),
        ];
    }

    /**
     * Commits between the installed revision and the tip of the branch.
     *
     * @return array{ahead:int,behind:int,files:array<int,array{filename:string,status:string}>,commits:array<int,array<string,mixed>>}
     */
    public function compare(string $base, string $head): array
    {
        $this->assertConfigured();

        $response = Http::get(
            self::API . '/repos/' . $this->repository . '/compare/' . rawurlencode($base) . '...' . rawurlencode($head),
            $this->headers(),
            30
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            // A missing base (fresh install, or history rewritten) is not an
            // error — we simply cannot show a file-level diff.
            Logger::info('GitHub compare unavailable', ['status' => $response['status']]);

            return ['ahead' => 0, 'behind' => 0, 'files' => [], 'commits' => []];
        }

        $json = $response['json'];
        $files = [];
        foreach ((array) ($json['files'] ?? []) as $file) {
            $files[] = [
                'filename' => (string) ($file['filename'] ?? ''),
                'status'   => (string) ($file['status'] ?? 'modified'),
            ];
        }

        $commits = [];
        foreach ((array) ($json['commits'] ?? []) as $commit) {
            $commits[] = [
                'sha'     => substr((string) ($commit['sha'] ?? ''), 0, 8),
                'message' => strtok((string) ($commit['commit']['message'] ?? ''), "\n"),
                'author'  => (string) ($commit['commit']['author']['name'] ?? ''),
                'date'    => (string) ($commit['commit']['author']['date'] ?? ''),
            ];
        }

        return [
            'ahead'   => (int) ($json['ahead_by'] ?? 0),
            'behind'  => (int) ($json['behind_by'] ?? 0),
            'files'   => $files,
            'commits' => $commits,
        ];
    }

    /** Read a single file from the repository (used to read app/version.php). */
    public function fileContents(string $path, ?string $ref = null): ?string
    {
        $this->assertConfigured();

        $response = Http::get(
            self::API . '/repos/' . $this->repository . '/contents/' . ltrim($path, '/') . '?ref=' . rawurlencode($ref ?? $this->branch),
            $this->headers() + ['Accept' => 'application/vnd.github.raw'],
            20
        );

        return $response['status'] === 200 ? $response['body'] : null;
    }

    /**
     * Download the branch (or a specific ref) as a ZIP archive.
     *
     * @return array{success:bool,path:?string,message:string,size:int}
     */
    public function downloadArchive(string $destination, ?string $ref = null): array
    {
        $this->assertConfigured();

        $url = self::API . '/repos/' . $this->repository . '/zipball/' . rawurlencode($ref ?? $this->branch);
        $response = Http::download($url, $destination, $this->headers(), 300);

        if ($response['status'] !== 200) {
            @unlink($destination);

            return [
                'success' => false,
                'path'    => null,
                'size'    => 0,
                'message' => $this->describeError($response, 'Could not download the update package'),
            ];
        }

        $size = (int) @filesize($destination);
        if ($size < 1024) {
            @unlink($destination);

            return ['success' => false, 'path' => null, 'size' => 0, 'message' => 'The downloaded package is empty or truncated.'];
        }

        return ['success' => true, 'path' => $destination, 'size' => $size, 'message' => 'Downloaded ' . human_size($size) . '.'];
    }

    /** @return array<int,array{tag:string,name:string,published:string,body:string}> */
    public function releases(int $limit = 10): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $response = Http::get(
            self::API . '/repos/' . $this->repository . '/releases?per_page=' . max(1, min(50, $limit)),
            $this->headers(),
            20
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            return [];
        }

        $releases = [];
        foreach ($response['json'] as $release) {
            $releases[] = [
                'tag'       => (string) ($release['tag_name'] ?? ''),
                'name'      => (string) ($release['name'] ?? ''),
                'published' => (string) ($release['published_at'] ?? ''),
                'body'      => mb_substr((string) ($release['body'] ?? ''), 0, 4000),
            ];
        }

        return $releases;
    }

    /** @param array<string,mixed> $response */
    private function describeError(array $response, string $prefix): string
    {
        return match ((int) $response['status']) {
            401, 403 => $prefix . ': GitHub rejected the credentials (HTTP ' . $response['status'] . ').',
            404      => $prefix . ': repository or branch not found.',
            0        => $prefix . ': ' . ($response['error'] !== '' ? $response['error'] : 'no response from GitHub.'),
            default  => $prefix . ': HTTP ' . $response['status'] . '.',
        };
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('The GitHub repository is not configured. Set it in Admin → Updates.');
        }
    }
}
