<?php

declare(strict_types=1);

namespace SimilarItems\Form;

use Laminas\Form\Element\Checkbox as CheckboxElement;
use Laminas\Form\Element\Number as NumberElement;
use Laminas\Form\Element\Select as SelectElement;
// Removed legacy select (match method) with prop1-4 deprecation.
use Laminas\Form\Element\Textarea as TextareaElement;
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
      ])
      ->add([
        'name' => 'similaritems_debug_log',
        'type' => CheckboxElement::class,
        'options' => [
          // @translate
          'label' => 'デバッグ: ログを出力（application.log）',
        ],
        'attributes' => [
          'id' => 'similaritems_debug_log',
        ],
      ]);

    // ==============================
    // Display options
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_limit',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '最大表示件数',
        ],
        'attributes' => [
          'id' => 'similaritems_limit',
          'min' => 1,
          'step' => 1,
          'value' => 6,
        ],
      ]);

    // ==============================
    // Jitter options (slight variability per reload)
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_jitter_enable',
        'type' => CheckboxElement::class,
        'options' => [
          // @translate
          'label' => '微揺らぎを有効化（リロード毎にわずかに変動）',
        ],
        'attributes' => [
          'id' => 'similaritems_jitter_enable',
        ],
      ])
      ->add([
        'name' => 'similaritems_jitter_pool_multiplier',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '候補プール倍率（例: 1.5）',
        ],
        'attributes' => [
          'id' => 'similaritems_jitter_pool_multiplier',
          'min' => 1,
          'step' => 0.1,
          'value' => 1.5,
        ],
      ]);

    // ==============================
    // Property mapping (advanced)
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_map_call_number',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 請求記号',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_call_number',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '請求記号のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_class_number',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 分類記号',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_class_number',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '分類記号のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_bibid',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 書誌ID',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_bibid',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '書誌IDのプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_ncid',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: NCID',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_ncid',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => 'NCIDのプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_author_id',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 著者ID',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_author_id',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '著者IDのプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_authorized_name',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 著者名典拠形',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_authorized_name',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '著者名典拠形のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_location',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 出版地',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_location',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '出版地のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_issued',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 出版年',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_issued',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '出版年のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_material_type',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 資料種別',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_material_type',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '資料種別のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_viewing_direction',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 閲覧方向',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_viewing_direction',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '閲覧方向のプロパティ…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_subject',
        'type' => PropertySelectElement::class,
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け: 主題（任意）',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_subject',
          'class' => 'chosen-select',
          // @translate
          'data-placeholder' => '主題（例: dcterms:subject）',
        ],
      ]);

    // ==============================
    // Weights (basic set)
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_weight_bibid',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 書誌ID一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_bibid',
          'min' => 0,
          'step' => 1,
          'value' => 10,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_ncid',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: NCID一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_ncid',
          'min' => 0,
          'step' => 1,
          'value' => 8,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_author_id',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 著者ID一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_author_id',
          'min' => 0,
          'step' => 1,
          'value' => 6,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_authorized_name',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 著者名典拠形一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_authorized_name',
          'min' => 0,
          'step' => 1,
          'value' => 4,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_subject',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 主題一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_subject',
          'min' => 0,
          'step' => 1,
          'value' => 4,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_domain_bucket',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 分野バケット一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_domain_bucket',
          'min' => 0,
          'step' => 1,
          'value' => 3,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_call_shelf',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 請求記号の棚記号一致',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_call_shelf',
          'min' => 0,
          'step' => 1,
          'value' => 2,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_class_proximity',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '重み: 分類番号の近さ（閾値内）',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_class_proximity',
          'min' => 0,
          'step' => 1,
          'value' => 1,
        ],
      ])
      ->add([
        'name' => 'similaritems_class_proximity_threshold',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => '閾値: 分類番号の差（例: 5）',
        ],
        'attributes' => [
          'id' => 'similaritems_class_proximity_threshold',
          'min' => 0,
          'step' => 1,
          'value' => 5,
        ],
      ]);

    // ==============================
    // Serendipity options
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_serendipity_demote_same_bibid',
        'type' => CheckboxElement::class,
        'options' => [
          // @translate
          'label' => 'セレンディピティ: 同一書誌ID（巻違い等）を強く抑制',
        ],
        'attributes' => [
          'id' => 'similaritems_serendipity_demote_same_bibid',
        ],
      ])
      ->add([
        'name' => 'similaritems_same_bibid_penalty',
        'type' => NumberElement::class,
        'options' => [
          // @translate
          'label' => 'ペナルティ: 同一書誌ID',
        ],
        'attributes' => [
          'id' => 'similaritems_same_bibid_penalty',
          'min' => 0,
          'step' => 1,
          'value' => 100,
        ],
      ])
      ->add([
        'name' => 'similaritems_serendipity_same_title_mode',
        'type' => SelectElement::class,
        'options' => [
          // @translate
          'label' => '同一ベースタイトルの扱い',
          'value_options' => [
            'allow' => '許可（推薦候補がなければ表示）',
            'exclude' => '完全除外（推薦候補がなければ関連のないアイテムをランダムに取得）',
          ],
        ],
        'attributes' => [
          'id' => 'similaritems_serendipity_same_title_mode',
          'value' => 'allow',
        ],
      ]);

    // ==============================
    // Title rules
    // ==============================
    $this
      ->add([
        'name' => 'similaritems_title_volume_separators',
        'type' => TextareaElement::class,
        'options' => [
          // @translate
          'label' => 'タイトルと巻号の区切り（1行に1つ、文字列）',
          'info' => '例: 半角スペース+カンマ+半角スペースの「 , 」など。設定すると、区切り以降はタイトルから除去して比較します。',
        ],
        'attributes' => [
          'id' => 'similaritems_title_volume_separators',
          'rows' => 3,
          'style' => 'font-family: monospace;',
          'placeholder' => ' , ',
        ],
      ]);

    // Domain bucket rules (JSON)
    $this
      ->add([
        'name' => 'similaritems_bucket_rules',
        'type' => TextareaElement::class,
        'options' => [
          // @translate
          'label' => '分野バケットのルール（JSON）',
          'info' => '請求記号・分類記号から分野を決定するOR/ANDルール。既定値をベースに編集できます。',
        ],
        'attributes' => [
          'id' => 'similaritems_bucket_rules',
          'rows' => 12,
          'style' => 'font-family: monospace;',
        ],
      ]);

    $inputFilter = $this->getInputFilter();
    $inputFilter
      ->add(['name' => 'similaritems_scope_site', 'required' => FALSE])
      ->add(['name' => 'similaritems_use_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_debug_log', 'required' => FALSE])
      ->add(['name' => 'similaritems_limit', 'required' => FALSE])
      ->add(['name' => 'similaritems_jitter_enable', 'required' => FALSE])
      ->add(['name' => 'similaritems_jitter_pool_multiplier', 'required' => FALSE]);

    // Mapping inputs are optional.
    $inputFilter
      ->add(['name' => 'similaritems_map_call_number', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_class_number', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_bibid', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_ncid', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_author_id', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_authorized_name', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_location', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_issued', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_material_type', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_viewing_direction', 'required' => FALSE])
      ->add(['name' => 'similaritems_map_subject', 'required' => FALSE]);

    $inputFilter
      ->add(['name' => 'similaritems_weight_bibid', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_ncid', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_author_id', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_authorized_name', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_subject', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_domain_bucket', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_call_shelf', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_class_proximity', 'required' => FALSE])
      ->add(['name' => 'similaritems_class_proximity_threshold', 'required' => FALSE])
      ->add(['name' => 'similaritems_bucket_rules', 'required' => FALSE])
      ->add(['name' => 'similaritems_serendipity_demote_same_bibid', 'required' => FALSE])
      ->add(['name' => 'similaritems_same_bibid_penalty', 'required' => FALSE])
      ->add(['name' => 'similaritems_serendipity_same_title_mode', 'required' => FALSE])
      ->add(['name' => 'similaritems_title_volume_separators', 'required' => FALSE]);
  }

}
