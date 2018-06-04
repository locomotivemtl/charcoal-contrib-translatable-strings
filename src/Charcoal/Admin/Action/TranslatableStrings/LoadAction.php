<?php

namespace Charcoal\Admin\Action\TranslatableStrings;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-admin'
use Charcoal\Admin\AdminAction;

/**
 * Action: Load a collection of translations from storage.
 *
 * ## Required Parameters
 *
 * - `translation_context` (_string_) — The context of the translations to load.
 * - `translation_lang`    (_string_) — The language of the translations to load.
 *
 * ## Response
 *
 * - `translations` (_array_) — All parameter matching translations.
 *
 * ## HTTP Status Codes
 *
 * - `200` — Successful; Object(s) loaded, if any.
 */
class LoadAction extends AdminAction
{
    /**
     * Paths to search.
     *
     * @var array
     */
    protected $paths;

    /**
     * Path to translations.
     *
     * @var string
     */
    protected $path;

    /**
     * A store of translations to return.
     *
     * @var array
     */
    protected $translations;

    /**
     * Supported input types.
     *
     * @var array
     */
    protected $allowedInputType = [
        'html',
        'wysiwyg',
        'text',
        'img',
        'image'
    ];

    /**
     * Retrieve the list of parameters to extract from the HTTP request.
     *
     * @return string[]
     */
    protected function validDataFromRequest()
    {
        return array_merge([
            'translation_context', 'translation_lang'
        ], parent::validDataFromRequest());
    }

    /**
     * Execute the endpoint.
     *
     * @param  RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $context = $request->getParam('translation_context');
        $lang    = $request->getParam('translation_lang');

        $trans = $this->parseTranslations(
            $this->getTranslations(),
            [
                'context' => $context,
                'lang'    => $lang
            ]
        );

        $this->translations = $trans;
        $this->setSuccess(true);

        return $response;
    }

    /**
     * Parse a list of translation strings according to a set of filters.
     *
     * @param array $translations A list of translations.
     * @param array $filters      A list of filters.
     * @return array
     */
    private function parseTranslations(array $translations, array $filters = [ 'context' => null, 'lang' => null ])
    {
        $parsedTranslations = [];
        foreach ($translations as $lang => $value) {
            // Must be the first occurrence of the the key.
            if ($filters['lang'] !== null && $filters['lang'] !== $lang) {
                continue;
            }

            ksort($value, SORT_ASC);

            $context = $filters['context'];
            if ($context) {
                $context = explode(',', $context);
            }

            // @todo Fix or remove this useless array_filter
            array_filter($value, function ($val, $key) use (&$parsedTranslations, $lang, $context) {
                if ($context) {
                    $pattern = '[%s]';
                    foreach ($context as &$scope) {
                        $scope = sprintf($pattern, $scope);
                        $scope = preg_quote($scope);
                    }

                    if (!preg_match('/(?:'.implode('|', $context).')/', $key)) {
                        return false;
                    }
                }

                $translationContext   = null;
                $translationCleanKey  = $key;
                $translationInputType = null;

                if (preg_match('|^\[([^\]]*)\]|', $key, $translationContext)) {
                    $translationCleanKey = str_replace($translationContext[0], '', $translationCleanKey);
                    $translationContext  = $translationContext[1];
                }

                if (preg_match('|:(?:\S*)$|', $key, $translationInputType)) {
                    $translationInputType = $translationInputType[0];
                    $type = ltrim($translationInputType, ':');

                    $translationCleanKey = str_replace($translationInputType, '', $translationCleanKey);

                    $translationInputType =
                        in_array($type, $this->allowedInputType) ?
                            $type : null;

                    $translationInputType = $type;
                }

                $parsedTranslations[] = [
                    'translation_key'        => $key,
                    'translation_value'      => $val,
                    'translation_lang'       => $lang,
                    'translation_input_type' => $translationInputType,
                    'translation_clean_key'  => $translationCleanKey,
                    'translation_context'    => $translationContext
                ];

                return true;
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $parsedTranslations;
    }

    /**
     * Retrieve all available translations by looping through all paths. Return format:
     * ```
     *    [
     *       'fr' => [
     *           'string' => 'translation',
     *           'string' => 'translation'
     *       ],
     *       'en' => [
     *           'string' => 'translation',
     *           'string' => 'translation'
     *       ]
     *    ]
     * ```
     * @return array
     */
    private function getTranslations()
    {
        $path = $this->path();

        if ($path) {
            $translations = $this->getTranslationsFromPath($path, 'mustache');
            $translations = array_replace($translations, $this->getTranslationsFromPath($path, 'php'));

            return $translations;
        } else {
            $paths = $this->paths();

            $translations = [];
            foreach ($paths as $path) {
                $translations = array_replace_recursive($translations, $this->getTranslationsFromPath($path, 'mustache'));
                $translations = array_replace_recursive($translations, $this->getTranslationsFromPath($path, 'php'));
            }

            return $translations;
        }
    }

    /**
     * Retrieve all translations for a given path and file type.
     *
     * @param  string $path     A file path.
     * @param  string $fileType A file extension|type.
     * @return array
     */
    private function getTranslationsFromPath($path, $fileType)
    {
        $basePath = $this->appConfig()->get('base_path');
        $files    = $this->getFilesRecursively(sprintf(
            '%s%s*.%s',
            $basePath,
            $path,
            $fileType
        ));
        $regex    = $this->fileRegex($fileType);
        $index    = 'text';

        $translations = [];
        foreach ($files as $key => $file) {
            $fileContent = file_get_contents($file);

            if (preg_match($regex, $fileContent)) {
                preg_match_all($regex, $fileContent, $matchedTranslations);

                $count   = count($matchedTranslations[$index]);
                $locales = $this->translator()->availableLocales();
                $i       = 0;
                for (; $i < $count; $i++) {
                    $originalString = $matchedTranslations[$index][$i];
                    foreach ($locales as $locale) {
                        $this->translator()->setLocale($locale);

                        // By calling translate, we make sure all existing translations are taken into consideration.
                        $translations[$locale][$originalString] = stripslashes(
                            $this->translator()->translate($originalString)
                        );
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Search for all pathnames recursively according to a pattern.
     *
     * @param string  $pattern The pattern to search.
     * @param integer $flags   The glob flags.
     * @todo  Add support for max depth.
     * @see   https://php.net/manual/en/function.glob.php#106595
     * @return array
     */
    private function getFilesRecursively($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', (GLOB_ONLYDIR | GLOB_NOSORT)) as $dir) {
            $files = array_merge($files, $this->getFilesRecursively($dir.'/'.basename($pattern), $flags));
        }

        return $files;
    }

    /**
     * Build a regular expression used for finding files within a filepath.
     *
     * @param  string $type File type (mustache|php).
     * @return string
     */
    private function fileRegex($type)
    {
        switch ($type) {
            case 'php':
                $function = $this->phpFunction();
                $regex    = '/->'.$function.'\(\s*\n*\r*(["\'])(?<text>(.|\n|\r|\n\r)*?)\s*\n*\r*\1\)/i';
                break;

            case 'mustache':
                $tag   = $this->mustacheTag();
                $regex = '/({{|\[\[)\s*#\s*'.$tag.'\s*(}}|\]\])(?<text>(.|\n|\r|\n\r)*?)({{|\[\[)\s*\/\s*'.$tag.'\s*(}}|\]\])/i';
                break;

            default:
                $regex = '/({{|\[\[)\s*#\s*_t\s*(}}|\]\])(?<text>(.|\n|\r|\n\r)*?)({{|\[\[)\s*\/\s*_t\s*(}}|\]\])/i';
                break;
        }

        return $regex;
    }

    /**
     * Retrieve the path to translations.
     *
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * Retrieve the paths to search.
     *
     * @return string
     */
    public function paths()
    {
        if ($this->paths === null) {
            $this->paths = $this->appConfig->get('translator.parser.view.paths') ?:
                $this->appConfig->get('view.paths');

            /** @todo Hardcoded; Change this! */
            $this->paths[] = 'src/';
        }

        return $this->paths;
    }

    /**
     * Retrieve the function found in PHP files used for translating strings.
     *
     * @return string
     */
    private function phpFunction()
    {
        return 'translate';
    }

    /**
     * Retrieve the tag found in Mustache files used for translating strings.
     *
     * @return string
     */
    private function mustacheTag()
    {
        return '_t';
    }

    /**
     * Retrieve the results of the action.
     *
     * @return array
     */
    public function results()
    {
        return $this->translations;
    }
}
