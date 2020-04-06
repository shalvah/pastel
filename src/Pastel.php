<?php

namespace Shalvah\Pastel;

use Illuminate\Support\Str;
use Mni\FrontYAML\Parser;
use Windwalker\Renderer\BladeRenderer;

class Pastel
{

    /**
     * Generate the API documentation using the markdown and include files
     */
    public function generate(
        string $sourceFolder,
        ?string $destinationFolder = '',
        $config = ['logo' => false]
    )
    {
        if (Str::endsWith($sourceFolder, '.md')) {
            // We're given just the path to a file, we'll use default assets
            $sourceMarkdownFile = $sourceFolder;
            $assetsFolder = __DIR__ . '/../resources';
        } else {
            if (!is_dir($sourceFolder)) {
                throw new \InvalidArgumentException("Source folder $sourceFolder is not a directory.");
            }

            // Valid source directory
            $sourceMarkdownFile = $sourceFolder . '/index.md';
            $assetsFolder = $sourceFolder;
        }

        $includesFolder = $sourceFolder . '/includes';

        if (empty($destinationFolder)) {
            // If no destination is supplied, place it in the parent of the source path
            $destinationFolder = dirname($sourceFolder);
        }

        $parser = new Parser();

        $document = $parser->parse(file_get_contents($sourceMarkdownFile));

        $frontmatter = $document->getYAML();
        $html = $document->getContent();

        $renderer = new BladeRenderer(
            [__DIR__ . '/../resources/views'],
            ['cache_path' => __DIR__ . '/_tmp']
        );

        // Parse and include optional include markdown files
        if (isset($frontmatter['includes'])) {
            foreach ($frontmatter['includes'] as $include) {
                if (file_exists($include_file = $includesFolder . '/_' . $include . '.md')) {
                    $document = $parser->parse(file_get_contents($include_file));
                    $html .= $document->getContent();
                }
            }
        }

        if (empty($frontmatter['last_updated'])) {
            $frontmatter['last_updated'] = date("F j Y", filemtime($sourceMarkdownFile));
        }

        // Allow overriding logo set in front matter from config
        $frontmatter['logo'] = $config['logo'] ?: $frontmatter['logo'] ?? false;

        $output = $renderer->render('index', [
            'page' => $frontmatter,
            'content' => $html,
        ]);

        if (!is_dir($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        file_put_contents($destinationFolder . '/index.html', $output);

        // Copy assets
        rcopy($assetsFolder . '/images/', $destinationFolder . '/images');
        rcopy($assetsFolder . '/css/', $destinationFolder . '/css');
        rcopy($assetsFolder . '/js/', $destinationFolder . '/js');
        rcopy($assetsFolder . '/fonts/', $destinationFolder . '/fonts');
    }
}