<?php

namespace Charcoal\Admin\Action\TranslatableStrings;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-admin'
use Charcoal\Admin\AdminAction;

/**
 * Action: Save a list of translations to a CSV file.
 *
 * ## Required Parameters
 *
 * - `translation_lang`  (_string_) — The language of the translations to update.
 * - `translation_key`   (_string_) — The key of the translations to update.
 * - `translation_value` (_string_) — The value of the translations to update
 *
 * ## Response
 *
 * - `success` (_boolean_) — TRUE
 *
 * ## HTTP Status Codes
 *
 * - `200` — Successful; Object has been updated
 */
class UpdateAction extends AdminAction
{
    /**
     * A store of request parameters.
     *
     * @var array
     */
    protected $item;

    /**
     * Retrieve the list of parameters to extract from the HTTP request.
     *
     * @return string[]
     */
    protected function validDataFromRequest()
    {
        return array_merge([
            'translation_lang', 'translation_value', 'translation_key'
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

        $this->item = $request->getParams();

        $base   = $this->appConfig()->get('base_path');
        $output = 'translations/';
        $domain = 'messages';

        $paramLang  = $request->getParam('translation_lang');
        $paramValue = $request->getParam('translation_value');
        $paramKey   = $request->getParam('translation_key');

        $dirPath  = str_replace('/', DIRECTORY_SEPARATOR, $base.$output);
        $filePath = str_replace('/', DIRECTORY_SEPARATOR, $base.$output.$domain.'.'.$paramLang.'.csv');

        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $translations = [];
        if (file_exists($filePath)) {
            $file = fopen($base.$output.$domain.'.'.$paramLang.'.csv', 'r');

            while ($csv = fgetcsv($file, 0, $this->separator(), $this->enclosure())) {
                $translations[$csv[0]] = $csv[1];
            }

            fclose($file);
        }

        $translations[$paramKey] = $paramValue;

        ksort($translations, SORT_ASC);

        $file = fopen($base.$output.$domain.'.'.$paramLang.'.csv', 'w+');

        foreach ($translations as $key => $translation) {
            $data = [ $key, $translation ];
            fputcsv($file, $data, $this->separator(), $this->enclosure());
        }

        fclose($file);

        $this->setSuccess(true);

        return $response;
    }

    /**
     * Retrieve the default enclosure for a CSV file.
     *
     * @return string
     */
    private function enclosure()
    {
        return '"';
    }

    /**
     * Retrieve the default separator for a CSV file.
     *
     * @return string
     */
    private function separator()
    {
        return ';';
    }

    /**
     * Retrieve the results of the action.
     *
     * @return array
     */
    public function results()
    {
        return $this->item;
    }
}
