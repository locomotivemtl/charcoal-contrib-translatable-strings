<?php

namespace Charcoal\Admin\Widget;

use Charcoal\Admin\AdminWidget;
use Charcoal\Translator\TranslatorAwareTrait;
use Pimple\Container;

/**
 * A widget for translating strings found in CSV files.
 */
class TranslatableStringsWidget extends AdminWidget
{
    /**
     * The specified translation context to filter the data.
     *
     * @var string|array
     */
    protected $translatableContext;

    /**
     * A flag determining if we should show the context column in the front-end grid.
     *
     * @var boolean
     */
    protected $showContext;

    /**
     * A flag determining the availability of filtering in the front-end.
     *
     * @var boolean
     */
    protected $hasFiltering;

    /**
     * Retrieve the widget's default data options for JavaScript components.
     *
     * @return array
     */
    protected function defaultWidgetData()
    {
        return [
            'translatable_context' => null,
            'show_context'         => true,
            'has_filtering'        => true
        ];
    }

    /**
     * Retrieve the widget's data options for JavaScript components.
     *
     * @return array
     */
    public function widgetDataForJs()
    {
        return array_merge($this->defaultWidgetData(), $this->parseWidgetData());
    }

    /**
     * Parse the widget data.
     *
     * @return array
     */
    public function parseWidgetData()
    {
        $data = [
            'translatable_context' => $this->translatableContext(),
            'show_context'         => $this->showContext(),
            'has_filtering'        => $this->hasFiltering()
        ];

        return array_filter($data, function ($val) {
            return $val !== null;
        });
    }

    /**
     * Retreive the specified translation context to filter the data.
     *
     * @return string
     */
    public function translatableContext()
    {
        return $this->translatableContext;
    }

    /**
     * Set the specified translation context to filter the data.
     *
     * @param string $context A filter string.
     * @return self
     */
    public function setTranslatableContext($context)
    {
        $this->translatableContext = $context;

        return $this;
    }

    /**
     * Determines if we should show the context column in the front-end grid.
     *
     * @return boolean
     */
    public function showContext()
    {
        return $this->showContext;
    }

    /**
     * Set the flag determining if we should show the context column in the front-end grid.
     *
     * @param boolean $flag A truthy state.
     * @return self
     */
    public function setShowContext(bool $flag)
    {
        $this->showContext = boolval($flag);

        return $this;
    }

    /**
     * Determines the availability of filtering on the front-end.
     *
     * @return boolean
     */
    public function hasFiltering()
    {
        return $this->hasFiltering;
    }

    /**
     * Set the flag determining the availability of filtering on the front-end.
     *
     * @param boolean $flag A truthy state.
     * @return self
     */
    public function setHasFiltering(bool $flag)
    {
        $this->hasFiltering = boolval($flag);

        return $this;
    }
}
