<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class TaskWorkbookDto
{
    /**
     * @param list<TaskDefinition> $tasks
     * @param array<string, PromptDefinition> $prompts
     * @param array<string, array{name:string,inputs:list<string>,prompt_name:string}> $artifacts
     * @param array<string, TaskInputDto> $inputs
     * @param list<array<string, string>> $doneArtifacts
     */
    public function __construct(
        public array $tasks,
        public array $prompts,
        public array $artifacts,
        public array $inputs,
        public array $doneArtifacts,
    ) {
    }
}
