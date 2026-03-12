<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Reporting;

use Doctrinify\Tools\Tasks\Storage\TaskArtifactWriter;
use Doctrinify\Tools\Tasks\Storage\TaskResultRepository;

final class TaskReportService
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?TaskResultRepository $resultRepository = null,
        private readonly ?TaskArtifactWriter $artifactWriter = null,
        private readonly ?HtmlReportBuilder $reportBuilder = null,
        private readonly ?BrowserOpener $browserOpener = null,
    ) {
    }

    /**
     * @return array{report_path:string,count:int,opened:bool}
     */
    public function build(string $name, string $storageRoot, bool $open): array
    {
        $repository = $this->resultRepository ?? new TaskResultRepository($this->projectRoot);
        $writer = $this->artifactWriter ?? new TaskArtifactWriter($this->projectRoot);
        $builder = $this->reportBuilder ?? new HtmlReportBuilder();
        $opener = $this->browserOpener ?? new BrowserOpener();

        $results = $repository->findByTaskSet($storageRoot, $name);
        $html = $builder->build($name, $results);

        $reportPath = $writer->reportPath($storageRoot, $name);
        $dir = dirname($reportPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create report directory: %s', $dir));
        }
        file_put_contents($reportPath, $html);

        $opened = false;
        if ($open) {
            $opened = $opener->open($reportPath);
        }

        return [
            'report_path' => $reportPath,
            'count' => count($results),
            'opened' => $opened,
        ];
    }
}
