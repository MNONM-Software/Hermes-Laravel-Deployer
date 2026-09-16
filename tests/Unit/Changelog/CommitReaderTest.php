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

    public function test_it_finds_the_last_tag_even_when_it_is_not_reachable_from_head(): void
    {
        // El caso real: deploy:release se corre desde develop y el tag lo puso
        // el CI sobre main. `git describe` no lo ve, y sin él el comando redacta
        // toda la historia del repo y propone 1.0.0.
        $this->commit('feat(stock): lo viejo');
        $this->git('checkout -q -b main-release');
        $this->commit('chore: lo que se taggeo');
        $this->git('tag v1.4.0');
        $this->git('checkout -q main');
        $this->commit('fix(remitos): lo nuevo');

        $result = (new CommitReader($this->repo))->sinceLastTag();

        $this->assertSame('v1.4.0', $result['tag']);
        $this->assertSame(['fix(remitos): lo nuevo'], $result['commits']);
    }

    public function test_it_takes_the_highest_version_and_not_the_last_one_created(): void
    {
        $this->commit('feat(stock): lo viejo');
        $this->git('tag v1.10.0');
        $this->git('tag v1.9.0');

        $this->assertSame('v1.10.0', (new CommitReader($this->repo))->sinceLastTag()['tag']);
    }

    public function test_a_tag_that_is_not_a_version_does_not_count_as_one(): void
    {
        $this->commit('feat(stock): lo primero');
        $this->git('tag sprint-3');

        $result = (new CommitReader($this->repo))->sinceLastTag();

        $this->assertNull($result['tag']);
        $this->assertSame(['feat(stock): lo primero'], $result['commits']);
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
