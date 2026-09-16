<?php

namespace Mnonm\HermesDeployer\Changelog;

use RuntimeException;

final class CommitReader
{
    public function __construct(private readonly string $repositoryPath) {}

    /** @return array{tag: ?string, commits: list<string>} */
    public function sinceLastTag(): array
    {
        // "Esto no es un repo" es un estado legítimo y se contesta vacío. Lo que
        // NO se atrapa es que git falle por otra razón —binario ausente, permisos,
        // repo corrupto—: eso tiene que explotar, porque si no el comando redacta
        // una entrada vacía y nadie se entera de que el entorno está roto.
        if (! is_dir($this->repositoryPath.'/.git')) {
            return ['tag' => null, 'commits' => []];
        }

        $tag = $this->lastTag();

        $range = $tag === null ? 'HEAD' : "{$tag}..HEAD";

        // --reverse porque `git log` devuelve del más nuevo al más viejo, y la
        // entrada del changelog se lee en el orden en que pasaron las cosas.
        $log = $this->git(['log', '--reverse', '--no-merges', '--pretty=format:%s', $range]);

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
