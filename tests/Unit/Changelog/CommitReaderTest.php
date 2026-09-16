<?php

namespace Mnonm\HermesDeployer\Tests\Unit\Changelog;

use Mnonm\HermesDeployer\Changelog\CommitReader;
use PHPUnit\Framework\TestCase;

class CommitReaderTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir().'/hermes-repo-'.uniqid();
        mkdir($this->repo, 0777, true);

        $this->git('init -q -b main');
        $this->git('config user.email test@example.com');
        $this->git('config user.name test');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->repo);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.'/'.$item;

            if (is_dir($full) && ! is_link($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }

    private function git(string $args): void
    {
        exec('git -C '.escapeshellarg($this->repo).' '.$args.' 2>&1');
    }

    private function commit(string $message): void
    {
        file_put_contents($this->repo.'/'.uniqid().'.txt', 'x');
        $this->git('add -A');
        $this->git('commit -q -m '.escapeshellarg($message));
    }

    public function test_it_reads_every_commit_when_there_is_no_tag(): void
    {
        $this->commit('feat(stock): lo primero');
        $this->commit('fix(remitos): lo segundo');

        $result = (new CommitReader($this->repo))->sinceLastTag();

        $this->assertNull($result['tag']);
        $this->assertSame(
            ['feat(stock): lo primero', 'fix(remitos): lo segundo'],
            $result['commits']
        );
    }

    public function test_it_reads_only_the_commits_after_the_last_tag(): void
    {
        $this->commit('feat(stock): lo viejo');
        $this->git('tag v1.4.0');
        $this->commit('fix(remitos): lo nuevo');

        $result = (new CommitReader($this->repo))->sinceLastTag();

        $this->assertSame('v1.4.0', $result['tag']);
        $this->assertSame(['fix(remitos): lo nuevo'], $result['commits']);
    }

    public function test_it_returns_nothing_outside_a_git_repository(): void
    {
        $notARepo = sys_get_temp_dir().'/hermes-not-a-repo-'.uniqid();
        mkdir($notARepo, 0777, true);

        $result = (new CommitReader($notARepo))->sinceLastTag();

        $this->assertNull($result['tag']);
        $this->assertSame([], $result['commits']);
    }
}
