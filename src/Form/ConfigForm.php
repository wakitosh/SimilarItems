<?php

declare(strict_types=1);

namespace SimilarItems\Form;

use Laminas\Form\Element\Checkbox as CheckboxElement;
use Laminas\Form\Element\Number as NumberElement;
use Laminas\Form\Element\Select as SelectElement;
use Omeka\Form\Element\PropertySelect as PropertySelectElement;
use Laminas\Form\Form;

/**
 * Similar Items module configuration form.
 */
class ConfigForm extends Form {

  /**
   * Initialize form elements for Similar Items module settings.
   */
  public function init(): void {
    $this
      ->add([
        'name' => 'similaritems_scope_site',
        'type' => CheckboxElement::class,
        'options' => [
    // @translate
          'label' => 'サイト内のみを対象',
        ],
        'attributes' => [
          'id' => 'similaritems_scope_site',
        ],
      ])

      ->add([
        'name' => 'similaritems_use_item_sets',
        'type' => CheckboxElement::class,
        'options' => [
    // @translate
          'label' => 'アイテムセットを類似判定に使用',
        ],
        'attributes' => [
          'id' => 'similaritems_use_item_sets',
        ],
      ])

      ->add([
        'name' => 'similaritems_weight_item_sets',
        'type' => NumberElement::class,
        'options' => [
    // @translate
          'label' => 'アイテムセットの重み',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_item_sets',
          'min' => 0,
          'step' => 1,
          'value' => 3,
        ],
      ]);

    for ($i = 1; $i <= 4; $i++) {
      $this
        ->add([
          'name' => "similaritems_prop{$i}_term",
          'type' => PropertySelectElement::class,
          'options' => [
      // @translate
            'label' => sprintf('プロパティ %d', $i),
            'empty_option' => '',
            'term_as_value' => TRUE,
            'use_hidden_element' => TRUE,
          ],
          'attributes' => [
            'id' => "similaritems_prop{$i}_term",
            'class' => 'chosen-select',
                // @translate
            'data-placeholder' => 'プロパティを選択…',
          ],
        ])
        ->add([
          'name' => "similaritems_prop{$i}_match",
          'type' => SelectElement::class,
          'options' => [
      // @translate
            'label' => 'マッチ方法',
            'value_options' => [
      // @translate
              'eq' => '完全一致',
      // @translate
              'cont' => '含む（部分一致）',
      // @translate
              'in' => '語彙内（カンマ区切りの辞書）',
            ],
          ],
          'attributes' => [
            'id' => "similaritems_prop{$i}_match",
            'value' => 'eq',
            'class' => 'chosen-select',
          ],
        ])
        ->add([
          'name' => "similaritems_prop{$i}_weight",
          'type' => NumberElement::class,
          'options' => [
      // @translate
            'label' => '重み',
          ],
          'attributes' => [
            'id' => "similaritems_prop{$i}_weight",
            'min' => 0,
            'step' => 1,
            'value' => 1,
          ],
        ]);
    }

    $this
      ->add([
        'name' => 'similaritems_terms_per_property',
        'type' => NumberElement::class,
        'options' => [
    // @translate
          'label' => 'プロパティごとの上限語数',
        ],
        'attributes' => [
          'id' => 'similaritems_terms_per_property',
          'min' => 1,
          'step' => 1,
          'value' => 10,
        ],
      ])
      ->add([
        'name' => 'similaritems_pool_multiplier',
        'type' => NumberElement::class,
        'options' => [
    // @translate
          'label' => '候補プール倍率',
        ],
        'attributes' => [
          'id' => 'similaritems_pool_multiplier',
          'min' => 1,
          'step' => 1,
          'value' => 5,
        ],
      ]);

    $inputFilter = $this->getInputFilter();
    $inputFilter
      ->add(['name' => 'similaritems_scope_site', 'required' => FALSE])
      ->add(['name' => 'similaritems_use_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop1_term', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop1_match', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop1_weight', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop2_term', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop2_match', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop2_weight', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop3_term', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop3_match', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop3_weight', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop4_term', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop4_match', 'required' => FALSE])
      ->add(['name' => 'similaritems_prop4_weight', 'required' => FALSE])
      ->add(['name' => 'similaritems_terms_per_property', 'required' => FALSE])
      ->add(['name' => 'similaritems_pool_multiplier', 'required' => FALSE]);
  }

}
