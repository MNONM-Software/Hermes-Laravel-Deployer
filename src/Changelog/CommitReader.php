<?php

namespace Mnonm\HermesDeployer\Changelog;

use RuntimeException;

final class CommitReader
{
    public function __construct(private readonly string $repositoryPath) {}

    /** @return array{tag: ?string, commits: list<string>} */
    public function sinceLastTag(): array
    {
        $tag = $this->lastTag();

        $range = $tag === null ? 'HEAD' : "{$tag}..HEAD";

        try {
            $log = $this->git(['log', '--no-merges', '--reverse', '--pretty=format:%s', $range]);
        } catch (RuntimeException) {
            // Fuera de un repo de git, o en uno sin commits: no hay nada que
            // redactar y no es un error. El guardado va también acá y no sólo en
            // lastTag(), que es donde es fácil olvidarlo.
            return ['tag' => $tag, 'commits' => []];
        }

        $commits = array_values(array_filter(
            array_map('trim', explode("\n", $log)),
            static fn (string $line): bool => $line !== ''
        ));

        return ['tag' => $tag, 'commits' => $commits];
    }

    private function lastTag(): ?string
    {
        try {
            $tag = trim($this->git(['describe', '--tags', '--abbrev=0']));
        } catch (RuntimeException) {
            return null;
        }

        return $tag === '' ? null : $tag;
    }

    /** @param  list<string>  $args */
    private function git(array $args): string
    {
        // Nunca un shell: argv, como el runner de Hermes.
        $process = proc_open(
            array_merge(['git', '-C', $this->repositoryPath], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if ($process === false) {
            throw new RuntimeException('no se pudo lanzar git');
        }

        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException('git falló: '.$err);
        }

        return $out;
    }
}
