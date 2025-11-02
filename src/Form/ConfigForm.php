<?php

declare(strict_types=1);

namespace SimilarItems\Form;

use Laminas\Form\Element\Checkbox as CheckboxElement;
use Laminas\Form\Element\Number as NumberElement;
use Laminas\Form\Element\Select as SelectElement;
use Laminas\Form\Element\Textarea as TextareaElement;
use Laminas\Form\Fieldset;
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
        'type' => Fieldset::class,
        'name' => 'similaritems_group_basic',
        'options' => [
          // @translate
          'label' => '基本設定',
          'info' => '対象範囲や表示件数など、全体の基本動作を設定します。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_basic_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '対象範囲や表示件数など、全体の基本動作を設定します。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_basic_info',
          'style' => 'display:none;',
        ],
      ])
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
        'name' => 'similaritems_debug_log',
        'type' => CheckboxElement::class,
        'options' => [
                  // @translate
          'label' => 'デバッグログを有効化 (logs/application.log)',
        ],
        'attributes' => [
          'id' => 'similaritems_debug_log',
        ],
      ])
    // 表示件数は上部に配置.
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
    // Shelf seeding (optional)
    // ==============================
    $this
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_shelf_seed',
        'options' => [
          // @translate
          'label' => '候補拡大',
          'info' => '請求記号（棚）や共有アイテムセットから候補を追加します。棚のスコア加算は別設定（重み: 棚記号）で制御されます。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_shelf_seed_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '請求記号（棚）や共有アイテムセットから候補を追加します。棚のスコア加算は別設定（重み: 棚記号）で制御されます。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_shelf_seed_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_use_item_sets',
        'type' => 'checkbox',
        'options' => [
          'label' => 'アイテムセットを類似判定に使用（候補拡大）',
          'info' => '候補を追加する設定です。共有アイテムセットに属するアイテムを候補に加えます。スコア加算量は「重み: アイテムセット一致」で制御されます。',
        ],
      ])
      ->add([
        'name' => 'similaritems_use_shelf_seeding',
        'type' => 'checkbox',
        'options' => [
                  // @translate
          'label' => '棚情報を類似判定に使用（候補拡大）',
                  // @translate
          'info' => '候補を追加する設定です。請求記号の先頭一致で同じ棚のアイテムを候補に加えます。なお、同一棚のスコア加算は「重み: 棚記号」で独立して制御されます。',
        ],
        'attributes' => [
          'id' => 'similaritems_use_shelf_seeding',
        ],
      ])
      ->add([
        'name' => 'similaritems_shelf_seed_limit',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '棚情報で追加する最大候補数（候補拡大の上限）',
        ],
        'attributes' => [
          'id' => 'similaritems_shelf_seed_limit',
          'min' => 1,
          'step' => 1,
          'value' => 50,
        ],
      ]);

    // ==============================
    // Jitter options (slight variability per reload)
    // ==============================
    $this
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_jitter',
        'options' => [
          // @translate
          'label' => '微揺らぎ',
          'info' => 'リロードごとに結果にわずかなランダム性を与え、同点候補の並び替えや多様性を促進します。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_jitter_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => 'リロードごとに結果にわずかなランダム性を与え、同点候補の並び替えや多様性を促進します。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_jitter_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_jitter_enable',
        'type' => CheckboxElement::class,
        'options' => [
                  // @translate
          'label' => '微揺らぎを有効化 (リロード毎に結果を変動)',
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
          'label' => '候補プール倍率',
                  // @translate
          'info' => '表示件数に対する候補数の倍率（例: 1.5）。',
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
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping',
        'options' => [
          // @translate
          'label' => 'プロパティ対応付け',
          'info' => 'アイテムのプロパティと、類似候補の選択・スコアリングで用いるシグナルの対応を指定します。下位のグループで役割が異なります。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => 'アイテムのプロパティと、類似候補の選択・スコアリングで用いるシグナルの対応を指定します。下位のグループで役割が異なります。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping_select_weight',
        'options' => [
          // @translate
          'label' => '候補に追加＋スコア加算',
          'info' => '指定プロパティが一致するアイテムを候補に追加し、対応する重みに応じてスコアを加算します。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_select_weight_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '指定プロパティが一致するアイテムを候補に追加し、対応する重みに応じてスコアを加算します。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_select_weight_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_ncid',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: NCID（候補に追加＋スコア加算）',
          'info' => '同一NCIDのアイテムを候補に追加し、重みに応じてスコアを加算します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_ncid',
          'class' => 'chosen-select',
          'data-placeholder' => 'NCIDのプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_author_id',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 著者ID（候補に追加＋スコア加算）',
          'info' => '同一著者IDのアイテムを候補に追加し、重みに応じてスコアを加算します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_author_id',
          'class' => 'chosen-select',
          'data-placeholder' => '著者IDのプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_authorized_name',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 著者名典拠形（候補に追加＋スコア加算・弱）',
          'info' => '著者IDがない場合のフォールバック。候補への追加とスコア加算に使用しますが、IDより弱いシグナルです。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_authorized_name',
          'class' => 'chosen-select',
          'data-placeholder' => '著者名典拠形のプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_subject',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 主題（候補に追加＋スコア加算）',
          'info' => '同一主題のアイテムを候補に追加し、重みに応じてスコアを加算します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_subject',
          'class' => 'chosen-select',
          'data-placeholder' => '主題のプロパティを選択… (例: dcterms:subject)',
        ],
      ])
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping_shelf',
        'options' => [
          'label' => '候補拡大＋棚のスコア加算',
          'info' => '請求記号を使って棚単位で候補を拡大し、同一棚には「重み: 棚記号」によるスコア加算を行います。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_shelf_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '請求記号を使って棚単位で候補を拡大し、同一棚には「重み: 棚記号」によるスコア加算を行います。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_shelf_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_call_number',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 請求記号（候補拡大＋棚ボーナス）',
          'info' => '「棚情報を類似判定に使用」がオンのとき候補拡大に使用。常に、同一棚の候補には「重み: 棚記号」によるスコア加算が入ります。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_call_number',
          'class' => 'chosen-select',
          'data-placeholder' => '請求記号のプロパティを選択…',
        ],
      ])
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping_boosts',
        'options' => [
          'label' => '近接・一致系（スコア加算のみ）',
          'info' => '候補選択には使わず、同一・近接などの条件を満たした場合のみスコアを加算します（分野・分類・出版年・資料種別など）。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_boosts_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '候補選択には使わず、同一・近接などの条件を満たした場合のみスコアを加算します（分野・分類・出版年・資料種別など）。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_boosts_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_class_number',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 分類記号（加点のみ：分野バケット・分類近接）',
          'info' => '候補選択には使用しません。分野バケットと分類近接（閾値以内）の加点に使用します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_class_number',
          'class' => 'chosen-select',
          'data-placeholder' => '分類記号のプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_issued',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 出版年（加点のみ：発行年近接）',
          'info' => '候補選択には使用しません。現在のアイテムと一定年数以内（閾値）なら加点します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_issued',
          'class' => 'chosen-select',
          'data-placeholder' => '出版年のプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_material_type',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 資料種別（加点のみ：資料種別一致）',
          'info' => '候補選択には使用しません。資料種別が一致する場合に加点します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_material_type',
          'class' => 'chosen-select',
          'data-placeholder' => '資料種別のプロパティを選択…',
        ],
      ])
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping_penalty',
        'options' => [
          'label' => 'ペナルティ中心',
          'info' => '主に同一書誌の抑制などスコアの減点に用います。抑制を無効化した場合のみ候補選択にも使用されます。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_penalty_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '主に同一書誌の抑制などスコアの減点に用います。抑制を無効化した場合のみ候補選択にも使用されます。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_penalty_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_bibid',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 書誌ID（通常はペナルティ用）',
          'info' => '既定では「同一書誌を抑制」ペナルティにのみ使用します。「同一書誌を抑制」をオフにした場合のみ、候補選択＋加点に使用されます。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_bibid',
          'class' => 'chosen-select',
          'data-placeholder' => '書誌IDのプロパティを選択…',
        ],
      ])
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_mapping_debug',
        'options' => [
          'label' => 'デバッグ用',
          'info' => 'スコアには影響しません。デバッグのために値を確認する用途です。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_mapping_debug_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => 'スコアには影響しません。デバッグのために値を確認する用途です。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_mapping_debug_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_location',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 出版地（デバッグ用）',
          'info' => 'スコアには影響しません。デバッグ用に値を表示します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_location',
          'class' => 'chosen-select',
          'data-placeholder' => '出版地のプロパティを選択…',
        ],
      ])
      ->add([
        'name' => 'similaritems_map_viewing_direction',
        'type' => PropertySelectElement::class,
        'options' => [
          'label' => 'プロパティ対応付け: 閲覧方向（デバッグ用）',
          'info' => 'スコアには影響しません。デバッグ用に値を表示します。',
          'empty_option' => '',
          'term_as_value' => TRUE,
          'use_hidden_element' => TRUE,
        ],
        'attributes' => [
          'id' => 'similaritems_map_viewing_direction',
          'class' => 'chosen-select',
          'data-placeholder' => '閲覧方向のプロパティを選択…',
        ],
      ]);

    // ==============================
    // Weights (basic set)
    // ==============================
    $this
      ->add([
        'type' => Fieldset::class,
        'name' => 'similaritems_group_weights',
        'options' => [
          'label' => 'ウェイトと閾値',
          'info' => '各シグナルの加点（重み）と、近接系の判定に使う閾値を設定します。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_weights_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '各シグナルの加点（重み）と、近接系の判定に使う閾値を設定します。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_weights_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_bibid',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '重み: 書誌ID',
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
          'label' => '重み: NCID',
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
          'label' => '重み: 著者ID',
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
          'label' => '重み: 著者名典拠形',
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
          'label' => '重み: 主題',
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
          'label' => '重み: 分野バケット（スコア加算）',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_domain_bucket',
          'min' => 0,
          'step' => 1,
          'value' => 3,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_item_sets',
        'type' => NumberElement::class,
        'options' => [
          'label' => '重み: アイテムセット（スコア加算）',
          'info' => '候補に入ったアイテムが現在のアイテムと同じアイテムセットに属する場合のスコア加算。',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_item_sets',
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
          'label' => '重み: 棚記号（スコア加算）',
                  // @translate
          'info' => '候補拡大の有無とは独立です。棚情報の候補拡大がオフでも、同一棚ならスコア加算されます。',
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
          'label' => '重み: 分類記号（スコア加算）',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_class_proximity',
          'min' => 0,
          'step' => 1,
          'value' => 1,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_material_type',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '重み: 資料種別（スコア加算）',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_material_type',
          'min' => 0,
          'step' => 1,
          'value' => 2,
        ],
      ])
      ->add([
        'name' => 'similaritems_weight_issued_proximity',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '重み: 出版年（スコア加算）',
        ],
        'attributes' => [
          'id' => 'similaritems_weight_issued_proximity',
          'min' => 0,
          'step' => 1,
          'value' => 1,
        ],
      ])
      ->add([
        'name' => 'similaritems_issued_proximity_threshold',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '閾値: 出版年近接 (年)',
        ],
        'attributes' => [
          'id' => 'similaritems_issued_proximity_threshold',
          'min' => 0,
          'step' => 1,
          'value' => 5,
        ],
      ])
      ->add([
        'name' => 'similaritems_class_proximity_threshold',
        'type' => NumberElement::class,
        'options' => [
                  // @translate
          'label' => '閾値: 分類近接',
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
        'type' => Fieldset::class,
        'name' => 'similaritems_group_serendipity',
        'options' => [
          'label' => 'セレンディピティ',
          'info' => '同一書誌や同一タイトルの出現を抑えて、多様性を確保するための設定です。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_serendipity_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '同一書誌や同一タイトルの出現を抑えて、多様性を確保するための設定です。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_serendipity_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_serendipity_demote_same_bibid',
        'type' => CheckboxElement::class,
        'options' => [
                  // @translate
          'label' => 'セレンディピティ: 同一書誌を抑制',
                  // @translate
          'info' => '有効にすると、同じ書誌ID（巻違いなど）を持つアイテムのスコアを大幅に下げ、多様性を確保します。',
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
                      // @translate
            'allow' => '許可 (他に候補がなければ表示)',
                      // @translate
            'exclude' => '完全除外 (候補がなければランダム表示)',
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
        'type' => Fieldset::class,
        'name' => 'similaritems_group_title_rules',
        'options' => [
          'label' => 'タイトルルール',
          'info' => 'ベースタイトルの抽出に用いる区切り文字を指定します（巻号・番号等を除去）。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_title_rules_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => 'ベースタイトルの抽出に用いる区切り文字を指定します（巻号・番号等を除去）。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_title_rules_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_title_volume_separators',
        'type' => TextareaElement::class,
        'options' => [
                  // @translate
          'label' => 'タイトルと巻号の区切り文字',
                  // @translate
          'info' => 'ベースタイトルを判定するために、タイトルと巻号を区切る文字列を1行に1つ指定します (例: 「 , 」)。',
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
        'type' => Fieldset::class,
        'name' => 'similaritems_group_bucket_rules',
        'options' => [
          'label' => '分野バケット（JSON）',
          'info' => '請求記号・分類記号などから分野を判定するルールをJSONで定義します。誤りがあると無視されます。',
        ],
      ])
      ->add([
        'name' => 'similaritems_group_bucket_rules_info',
        'type' => 'text',
        'options' => [
          'label' => ' ',
          'info' => '請求記号・分類記号などから分野を判定するルールをJSONで定義します。誤りがあると無視されます。',
        ],
        'attributes' => [
          'id' => 'similaritems_group_bucket_rules_info',
          'style' => 'display:none;',
        ],
      ])
      ->add([
        'name' => 'similaritems_bucket_rules',
        'type' => TextareaElement::class,
        'options' => [
                  // @translate
          'label' => '分野バケットのルール (JSON)',
                  // @translate
          'info' => '請求記号や分類記号からアイテムの分野を判定するためのルールをJSON形式で定義します。',
        ],
        'attributes' => [
          'id' => 'similaritems_bucket_rules',
          'rows' => 12,
          'style' => 'font-family: monospace;',
        ],
      ]);

    $inputFilter = $this->getInputFilter();
    $inputFilter
      // Section description helpers (not submitted / ignored)
      ->add(['name' => 'similaritems_group_basic_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_shelf_seed_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_jitter_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_select_weight_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_shelf_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_boosts_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_penalty_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_mapping_debug_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_weights_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_serendipity_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_title_rules_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_group_bucket_rules_info', 'required' => FALSE])
      ->add(['name' => 'similaritems_scope_site', 'required' => FALSE])
      ->add(['name' => 'similaritems_use_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_item_sets', 'required' => FALSE])
      ->add(['name' => 'similaritems_debug_log', 'required' => FALSE])
      ->add(['name' => 'similaritems_use_shelf_seeding', 'required' => FALSE])
      ->add(['name' => 'similaritems_shelf_seed_limit', 'required' => FALSE])
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
      ->add(['name' => 'similaritems_weight_material_type', 'required' => FALSE])
      ->add(['name' => 'similaritems_weight_issued_proximity', 'required' => FALSE])
      ->add(['name' => 'similaritems_issued_proximity_threshold', 'required' => FALSE])
      ->add(['name' => 'similaritems_class_proximity_threshold', 'required' => FALSE])
      ->add(['name' => 'similaritems_bucket_rules', 'required' => FALSE])
      ->add(['name' => 'similaritems_serendipity_demote_same_bibid', 'required' => FALSE])
      ->add(['name' => 'similaritems_same_bibid_penalty', 'required' => FALSE])
      ->add(['name' => 'similaritems_serendipity_same_title_mode', 'required' => FALSE])
      ->add(['name' => 'similaritems_title_volume_separators', 'required' => FALSE]);
  }

}
