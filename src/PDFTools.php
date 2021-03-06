<?php
class PDFTools
{
    const DEFAULT_DPI_RESPOLUTION = 300;

    private $file_path;
    private $pages_count;
    private $pages_sizes;

    public function __construct($pdf_file_path)
    {
        $this->file_path = $pdf_file_path;
    }

    public function splitPdfToPath(string $target_path, $target_file_name = null)
    {
        $target_path = $this->outputTargetPathForSlitingPdfs($target_path, $target_file_name);

        // If one page just copy
        if ($this->pagesCount() == 1 and copy($this->file_path, $target_path)) {
            return [$target_path];
        }

        $pages_count = $this->splitToFolderAndReturnCreatePagesCount($target_path);
        $output_paths = $this->generateArrayCreatesPagesPaths($target_path, $pages_count);

        return $output_paths;
    }

    private function outputTargetPathForSlitingPdfs(string $target_path, $target_file_name)
    {
        $oryginal_file_name = $target_file_name ?? FilesUntils::getFileBasename($this->file_path);

        // Fix not allowed characters, like spaces, in file path
        $path_parts = pathinfo($oryginal_file_name);
        $safe_file_name = StringUntils::slug($path_parts['filename']) . '.' . $path_parts['extension'];

        $file_name = str_replace('.pdf', '_page%02d.pdf', $safe_file_name);
        $output_target_path = $target_path . '/' . $file_name;

        // if one page just copy
        if ($this->pagesCount() == 1) {
            $output_target_path = $target_path . '/' . $oryginal_file_name;
        }

        return $output_target_path;
    }

    private function splitToFolderAndReturnCreatePagesCount(string $target_path)
    {
        $split_results = shell_exec('gs -dNOSAFER -sDEVICE=pdfwrite ' .
                                      ' -dSAFER ' . // Disables the deletefile and renamefile operators
                                      ' -o ' . $target_path . // outname.%d.pdf
                                      ' ' . $this->file_path);

        $gs_parser = new GhostScriptDumpMediaSizesParser($split_results);

        return $gs_parser->getPagesFromSplitResults();
    }

    private function generateArrayCreatesPagesPaths(string $target_path, int $pages_count)
    {
        $created_files_paths = [];
        for ($i = 1; $i < ($pages_count + 1); ++$i) {
            $page_path = str_replace('%02d', sprintf('%02d', $i), $target_path);
            if (file_exists($page_path)) {
                $created_files_paths[] = $page_path;
            }
        }

        return $created_files_paths;
    }

    public function pagesCount()
    {
        return (new FileMeasurementPDF($this->file_path))->pagesCount();
    }

    public function convertToJpg(string $target_path, array $params = [])
    {
        $page = $params['page'] ?? 1;
        $quality = $params['jpg_quality'] ?? 100;
        $dpi = $params['jpg_dpi'] ?? 300;
        $subsample_antialiasing = $params['subsample_antialiasing'] ?? true;

        // We have some problems with get size by GhostScript, so we baypass it to pass values from pdfinfo
        $width_in_px = $params['width_in_px'] ?? null;
        $height_in_px = $params['height_in_px'] ?? null;

        if (!$width_in_px and !$height_in_px) {
            $measurement = new FileMeasurementPDF($this->file_path);
            $width_in_px = $measurement->widthInPx(['page' => $page]);
            $height_in_px = $measurement->heightInPx(['page' => $page]);
        }

        $file_name = FilesUntils::getFileBasename($this->file_path);

        $size_multiplier = 1;
        if ($subsample_antialiasing) {
            // 2 x bigger to fix not working antyaliasing in ghostscript
            $size_multiplier = 2;
        }

        shell_exec('gs -dNOSAFER -sDEVICE=jpeg ' .
            ' -o ' . $target_path .
            ' -dFirstPage=' . $page .
            ' -dLastPage=' . $page .
            ' -r' . $dpi .
            ' -dTextAlphaBits=4' . // subsample antialiasing
            ' -dGraphicsAlphaBits=4' . // subsample antialiasing
            ' -dJPEGQ=' . $quality .
            ' -g' . ($width_in_px * $size_multiplier) . 'x' . ($height_in_px * $size_multiplier) .
            ' -dPDFFitPage ' . $this->file_path .
            ' 2>/dev/null');

        if ($subsample_antialiasing) {
            ImagickUntils::resize($target_path, $target_path, $width_in_px, $height_in_px);
        }
    }
}
