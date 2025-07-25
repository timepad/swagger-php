<?php

/**
 * @license Apache 2.0
 */

namespace Swagger;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Exception;


/**
 * Extract swagger-php annotations from a [PHPDoc](http://en.wikipedia.org/wiki/PHPDoc) using Doctrine's DocParser.
 */
class Analyser
{
    /**
     * List of namespaces that should be detected by the doctrine annotation parser.
     * Set to false to load all detected classes.
     * @var array|false
     */
    public static $whitelist = ['Swagger\Annotations\\'];

    /**
     * Use @SWG\* for swagger annotations (unless overwritten by a use statement).
     */
    public static $defaultImports = ['swg' => 'Swagger\Annotations'];

    /**
     * Allows Annotation classes to know the context of the annotation that is being processed.
     * @var Context
     */
    public static $context;

    /**
     * @var DocParser
     */
    public $docParser;

    public function __construct($docParser = null)
    {
        if ($docParser === null) {
            $docParser = new DocParser();
            $docParser->setIgnoreNotImportedAnnotations(true);
            $docParser->setImports(static::$defaultImports);
        }
        $this->docParser = $docParser;
    }

    /**
     * Use doctrine to parse the comment block and return the detected annotations.
     *
     * @param string $comment a T_DOC_COMMENT.
     * @param Context $context
     * @return array Annotations
     */
    public function fromComment($comment, $context = null)
    {
        if ($context === null) {
            $context = new Context(['comment' => $comment]);
        } else {
            $context->comment = $comment;
        }
        try {
            self::$context = $context;
            if ($context->is('annotations') === false) {
                $context->annotations = [];
            }
            $comment = preg_replace_callback('/^[\t ]*\*[\t ]+/m', function ($match) {
                // Replace leading tabs with spaces.
                // Workaround for http://www.doctrine-project.org/jira/browse/DCOM-255
                return str_replace("\t", ' ', $match[0]);
            }, $comment);
            $annotations = $this->docParser->parse($comment, $context);
            self::$context = null;
            return $annotations;
        } catch (Exception $e) {
            self::$context = null;
            if (preg_match('/^(.+) at position ([0-9]+) in ' . preg_quote($context, '/') . '\.$/', $e->getMessage(), $matches)) {
                $errorMessage = $matches[1];
                $errorPos = $matches[2];
                $atPos = strpos($comment, '@');
                $context->line += substr_count($comment, "\n", 0, $atPos + $errorPos);
                $lines = explode("\n", substr($comment, $atPos, $errorPos));
                $context->character = strlen(array_pop($lines)) + 1; // position starts at 0 character starts at 1
                Logger::warning(new Exception($errorMessage . ' in ' . $context, $e->getCode(), $e));
            } else {
                Logger::warning($e);
            }
            return [];
        }
    }
}
