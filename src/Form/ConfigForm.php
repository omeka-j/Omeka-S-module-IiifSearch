<?php declare(strict_types=1);

namespace IiifSearch\Form;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Form;
use Omeka\Form\Element\PropertySelect;

/**
 * ConfigForm
 * 設定フォーム
 */
class ConfigForm extends Form
{
    use EventManagerAwareTrait;

    public function init(): void
    {
        $this
            // URL
            ->add([
                'name' => 'iiifsearch_url',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property supplying the url of the search api', // @translate
                    # 'info' => 'External or static manifests can be more customized and may be quicker to be loaded. Usually, the property is "dcterms:hasFormat" or "dcterms:isFormatOf".', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'iiifsearch_url',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select property',
                ],
            ])
        ;
        // 以下そのまま
        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'iiifsearch_url',
                'required' => false,
            ])
        ;

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}
