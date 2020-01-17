<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 *
 */
class DisplayCheckBoxes extends FormBase {

  /**
   * Drupal\Core\Path\CurrentPathStack definition.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */

  protected $currPath;

  /**
   * The link generator service.
   *
   * @var pathLink\Drupal\Core\Utility\LinkGeneratorInterface
   */

  protected $pathLink;

  /**
   * Class constructor.
   */
  public function __construct(CurrentPathStack $currPath, LinkGeneratorInterface $pathLink) {

    $this->currPath = $currPath;
    $this->pathLink = $pathLink;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(

      $container->get('path.current'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'heritage_ui_display_checkboxes';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {
    $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);

    // Get the textid from the current path.
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];

    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];

    $form['text_info'] = [
      '#type' => 'container',
      '#prefix' => '<div id="text-info">',
      '#suffix' => '</div>',
    ];

    if (isset($textid) && $textid > 0) {
      // Find the textname.
      $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
      // Load the node.
      $text_node = Node::load($textid);

      // Query to find the number of levels of a textid.
      $levels = db_query("SELECT field_levels_value FROM `node__field_levels` WHERE entity_id = :textid and bundle = :bundle ", [':textid' => $textid, ':bundle' => 'heritage_text'])->fetchField();
      $form['levels'] = [
        '#type' => 'hidden',
        '#value' => $levels,

      ];

      if ($levels == 1) {
        // Only chapter is present.
        $langcode = 'dv';
        $level_labels = explode(',', $text_node->field_level_labels->value);

        $form['text_info']['fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Select the level'),
          '#description' => $this->t('Choose the content'),
        ];

        // Query for chapters.
        $chapters = [];
        $query = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE '{$level_labels[0]}%' AND vid = :textname ORDER BY tid ASC", [':textname' => $textname])->fetchAll();
        // Done to set the default value.
        $chapter_selected_tid = $query[0]->tid;

        foreach ($query as $key => $value) {
          $chapters[$value->tid] = $value->name;
        }

        // If position parameter is present set the default value of the chapter field,
        // using the values of the position
        // Set the default values of chapter and sloka using $_GET.
        if (isset($_GET['position'])) {
          $position = $_GET['position'];
          $chapter_selected = $position;
          // The tid of the chapter is gotten from chapter number.
          $chapter_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :chapter_selected AND bundle = :textname", [':chapter_selected' => $chapter_selected, ':textname' => $textname])->fetchField();
          // If the ajax is not triggered, set the chapter tid from position
          // This variable is used to display all the slokas of a chapter.
          $chapter_selected_tid = $chapter_tid;

        }

        $form['text_info']['fieldset']['chapters'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[0]),
          '#required' => TRUE,
          '#options' => $chapters,
          '#default_value' => isset($chapter_tid) ? $chapter_tid : $query[0]->tid,
         // '#attributes' => ['onchange' => 'this.form.submit();'],
        ];

        if (isset($_GET['language'])) {
          $language = $_GET['language'];
          foreach ($languages as $lang) {
            if ($language == $lang->getName()) {
              $langcode = $lang->getId();
            }
          }

        }

        // Add a language field.
        $form['text_info']['fieldset']['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#required' => TRUE,
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,

          '#default_value' => $langcode,

        // '#attributes' => ['onchange' => 'this.form.submit();'],
        //     '#ajax' => [
        //   'callback' => ':: submitForm',
        //   'event' => 'change',
        // ],
        ];

        $form['previous_chapter'] = [
          '#type' => 'submit',
          '#value' => $this->t('&lt;&lt;'),
          '#submit' => ['::previous_level_submit'],

        ];
        $form['next_chapter'] = [
          '#type' => 'submit',
          '#value' => $this->t('&gt;&gt;'),
          '#submit' => ['::next_level_submit'],
        ];

      }

      if ($levels == 2) {

        // Select the level labels like Chapter, Sloka etc.
        $level_labels = explode(',', $text_node->field_level_labels->value);

        $langcode = 'dv';

        $form['text_info']['fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Select the levels '),
          '#description' => $this->t('Choose the content'),
        ];

        // Query for chapters.
        $chapters = [];

        $query = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE '{$level_labels[0]}%' AND vid = :textname ORDER BY tid ASC", [':textname' => $textname])->fetchAll();

        // Done to set the default value.
        // Chapter 1.
        $chapter_selected_tid = $query[0]->tid;

        foreach ($query as $key => $value) {
          $chapters[$value->tid] = $value->name;
        }

        // If position parameter is present set the default value of the chapter field,
        // using the values of the position
        // Set the default values of chapter and sloka using $_GET.
        if (isset($_GET['position'])) {
          $position = $_GET['position'];

          $var = explode('.', $position);
          $chapter_selected = $var[0];
          $sloka_selected = $var[1];

          // The tid of the chapter is gotten from chapter number.
          $chapter_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :chapter_selected AND bundle = :textname", [':chapter_selected' => $chapter_selected, ':textname' => $textname])->fetchField();
          // If the ajax is not triggered, set the chapter tid from position
          // This variable is used to display all the slokas of a chapter.
          $chapter_selected_tid = $chapter_tid;

        }

        $form['text_info']['fieldset']['chapters'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[0]),
          '#required' => TRUE,
          '#options' => $chapters,

          '#default_value' => isset($chapter_tid) ? $chapter_tid : $query[0]->tid,

          '#ajax' => [
            'event' => 'change',
            'wrapper' => 'chapter-formats',
            'callback' => '::_ajax_chapter_callback',
          ],

        ];

        // Calculate number of sublevels for each chapter.
        $slokas = [];

        // Ajax triggers when a chapter is selected.
        if (!empty($form_state->getTriggeringElement())) {
          // Gives the tid of chapter.
          $chapter_selected_tid = $form_state->getUserInput()['chapters'];

        }

        // print_r($sub_level_count);exit;
        $form['text_info']['fieldset']['chapter_formats'] = [
          '#type' => 'container',
          '#prefix' => '<div id="chapter-formats">',
          '#suffix' => '</div>',
        ];
        // print_r($chapter_selected_tid);exit;
        // calculate the sublevels of this chapter.
        if (isset($chapter_selected_tid)) {
          $sub_level_count = calculate_sublevel_number($textname, $chapter_selected_tid);
          for ($i = 1; $i <= $sub_level_count; $i++) {
            $slokas[$i] = $level_labels[1] . " " . $i;
          }

        }

        // If position parameter is present in the url, set the default value for the sloka,
        // using the position parameter, else set the default as Sloka 1.
        $form['text_info']['fieldset']['chapter_formats']['slokas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[1]),
          '#required' => TRUE,
          '#options' => $slokas,

          '#default_value' => isset($sloka_selected) ? $sloka_selected : 1,

        ];

        if (isset($_GET['language'])) {
          $language = $_GET['language'];
          foreach ($languages as $lang) {
            if ($language == $lang->getName()) {
              $langcode = $lang->getId();
            }
          }

        }

        // Add a language field.
        $form['text_info']['fieldset']['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#required' => TRUE,
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,

          '#default_value' => $langcode,

        // '#attributes' => ['onchange' => 'this.form.submit();'],
        //     '#ajax' => [
        //   'callback' => ':: submitForm',
        //   'event' => 'change',
        // ],
        ];

        // Navigatin Buttons.
        $form['previous_chapter'] = [
          '#type' => 'submit',
          '#value' => $this->t('&lt;&lt;'),
          '#submit' => ['::previous_level_submit'],

        ];

        $form['previous_sloka'] = [
          '#type' => 'submit',
          '#value' => $this->t('&lt;'),
          '#submit' => ['::previous_sublevel_submit'],

        ];

        $form['next_sloka'] = [
          '#type' => 'submit',
          '#value' => $this->t('&gt;'),
          '#submit' => ['::next_sublevel_submit'],

        ];

        $form['next_chapter'] = [
          '#type' => 'submit',
          '#value' => $this->t('&gt;&gt;'),
          '#submit' => ['::next_level_submit'],
        ];

      }

      if ($levels == 3) {
        $langcode = 'dv';
        $level_labels = explode(',', $text_node->field_level_labels->value);

        $form['text_info']['fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Select the levels '),
          '#description' => $this->t('Choose the content'),
        ];

        // Make query for Kandas.
        $kandas = [];
        $query = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE '{$level_labels[0]}%' AND vid = :textname ORDER BY tid ASC", [':textname' => $textname])->fetchAll();

        // Done to set the default values for kandas and slokas.
        // Kanda 1.
        $kanda_selected_tid = $query[0]->tid;
        $query_sarga = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE 'Sarga%' AND vid = :textname AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':textname' => $textname, ':parent_tid' => $kanda_selected_tid])->fetchAll();
        $sarga_selected_tid = $query_sarga[0]->tid;

        foreach ($query as $key => $value) {
          $kandas[$value->tid] = $value->name;
        }

        // If position parameter is present set the default value of the kandafield,
        // using the values of the position
        // Set the default values of sarga and sloka using $_GET.
        if (isset($_GET['position'])) {
          $position = $_GET['position'];
          $var = explode('.', $position);
          // print_r($var);exit;
          $kanda_selected = $var[0];
          // 1.1,1.2 etc
          $sarga_selected = $kanda_selected . '.' . $var[1];

          $sloka_selected = $var[2];
          // print_r($sloka_selected);exit;
          // The tid of the kanda.
          $kanda_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :kanda_selected AND bundle = :textname", [':kanda_selected' => $kanda_selected, ':textname' => $textname])->fetchField();

          $kanda_selected_tid = $kanda_tid;

          // Tid for sarga.
          $sarga_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :sarga_selected AND bundle = :textname", [':sarga_selected' => $sarga_selected, ':textname' => $textname])->fetchField();
          // print_r($sarga_tid);exit;
          $sarga_selected_tid = $sarga_tid;

        }

        $form['text_info']['fieldset']['kandas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[0]),
          '#required' => TRUE,
          '#options' => $kandas,

          '#default_value' => isset($kanda_tid) ? $kanda_tid : $query[0]->tid,

          '#ajax' => [
            'event' => 'change',
            'wrapper' => 'kanda-formats',
            'callback' => '::_ajax_kanda_callback',
          ],

        ];

        // Ajax triggers when a chapter is selected.
        if (!empty($form_state->getTriggeringElement())) {
          // Gives the tid of chapter.
          $kanda_selected_tid = $form_state->getUserInput()['kandas'];

        }

        // Sargas and slokas.
        $sargas = [];
        $slokas = [];

        $form['text_info']['fieldset']['kanda_formats'] = [
          '#type' => 'container',
          '#prefix' => '<div id="kanda-formats">',
          '#suffix' => '</div>',
        ];

        // Calculate the sargas of this kanda.
        if (isset($kanda_selected_tid)) {

          // Query for sarga.
          $query = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE 'Sarga%' AND vid = :textname AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':textname' => $textname, ':parent_tid' => $kanda_selected_tid])->fetchAll();
          // print_r($query);exit;
          foreach ($query as $key => $value) {
            $sargas[$value->tid] = $value->name;
          }

        }

        $form['text_info']['fieldset']['kanda_formats']['sargas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[1]),
          '#required' => TRUE,
          '#options' => $sargas,

          '#default_value' => isset($sarga_tid) ? $sarga_tid : $query_sarga[0]->tid,
          '#ajax' => [
            'event' => 'change',
            'wrapper' => 'sarga-formats',
            'callback' => '::_ajax_sarga_callback',
          ],

        ];

        $form['text_info']['fieldset']['sarga_formats'] = [
          '#type' => 'container',
          '#prefix' => '<div id="sarga-formats">',
          '#suffix' => '</div>',
        ];

        // Ajax triggers when a chapter is selected.
        if (!empty($form_state->getTriggeringElement())) {
          // Gives the tid of chapter.
          $sarga_selected_tid = $form_state->getUserInput()['sargas'];

        }

        if (isset($sarga_selected_tid)) {
          $sub_level_count = calculate_sublevel_number($textname, $sarga_selected_tid);
          for ($i = 1; $i <= $sub_level_count; $i++) {
            $slokas[$i] = $level_labels[2] . " " . $i;

          }
        }

        $form['text_info']['fieldset']['sarga_formats']['slokas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels[2]),
          '#required' => TRUE,
          '#options' => $slokas,

          '#default_value' => isset($sloka_selected) ? $sloka_selected : 1,

        ];

        if (isset($_GET['language'])) {
          $language = $_GET['language'];
          foreach ($languages as $lang) {
            if ($language == $lang->getName()) {
              $langcode = $lang->getId();
            }
          }

        }

        // Add a language field.
        $form['text_info']['fieldset']['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#required' => TRUE,
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,

          '#default_value' => $langcode,

        // '#attributes' => ['onchange' => 'this.form.submit();'],
        //     '#ajax' => [
        //   'callback' => '::  _ajax_callback_setValues',
        //   'event' => 'change',
        // ],
        ];

        $form['previous_sarga'] = [
          '#type' => 'submit',
          '#value' => $this->t('&lt;&lt;'),
          '#submit' => ['::previous_level_submit'],

        ];

        $form['previous_sloka'] = [
          '#type' => 'submit',
          '#value' => $this->t('&lt;'),
          '#submit' => ['::previous_sublevel_submit'],

        ];

        $form['next_sloka'] = [
          '#type' => 'submit',
          '#value' => $this->t('&gt;'),
          '#submit' => ['::next_sublevel_submit'],

        ];

        $form['next_sarga'] = [
          '#type' => 'submit',
          '#value' => $this->t('&gt;&gt;'),
          '#submit' => ['::next_level_submit'],
        ];

      }
    }

    $form['actions'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // $response = new AjaxResponse();
    $get = $_GET;
    $textid = $form_state->getValue('text');

    // Find the textname.
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();

    $levels = $form_state->getValue('levels');

    if ($levels == 1) {
      $chapter_tid = $form_state->getValue('chapters');
      // Get the chapter number from table taxonomy_term__field_position and see column field_positon_value.
      $chapter_number = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $chapter_tid, ':textname' => $textname])->fetchField();

      $position = $chapter_number;
      // Default language.
      $language = 'devanagari';
      $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);

      // Collect the langcode.
      $langcode = $form_state->getValue('selected_langcode');
      // This loop is for printing the languages as English, Hindi, Bengali etc
      // Else it gets printed as en,dv,bn.
      foreach ($languages as $lang) {
        if ($langcode == $lang->getId()) {
          $language = $lang->getName();
        }
      }

      $get['position'] = $position;
      $get['language'] = $language;

    }

    // Collect the chapter.
    if ($levels == 2) {
      $chapter_tid = $form_state->getValue('chapters');
      $sloka_number = $form_state->getValue('slokas');

      // Get the chapter number from table taxonomy_term__field_position and see column field_positon_value.
      $chapter_number = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $chapter_tid, ':textname' => $textname])->fetchField();

      $position = $chapter_number . '.' . $sloka_number;

      // Default language.
      $language = 'devanagari';
      $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);

      // Collect the langcode.
      $langcode = $form_state->getValue('selected_langcode');
      // This loop is for printing the languages as English, Hindi, Bengali etc
      // Else it gets printed as en,dv,bn.
      foreach ($languages as $lang) {
        if ($langcode == $lang->getId()) {
          $language = $lang->getName();
        }
      }

      // Attach position and language to url.
      $get['position'] = $position;
      $get['language'] = $language;

    }

    if ($levels == 3) {
      $kanda_tid = $form_state->getValue('kandas');
      $sarga_tid = $form_state->getValue('sargas');
      $sloka_number = $form_state->getValue('slokas');

      // Get the kanda, sarga number from table taxonomy_term__field_position and see column field_positon_value.
      $kanda_number = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $kanda_tid, ':textname' => $textname])->fetchField();
      $sarga_number = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $sarga_tid, ':textname' => $textname])->fetchField();

      // sarga_number shows as 1.1, hence split it and get the second value.
      $sarga_number = explode('.', $sarga_number);

      $position = $kanda_number . '.' . $sarga_number[1] . '.' . $sloka_number;
      // Default language.
      $language = 'devanagari';
      $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
      // Collect the langcode.
      $langcode = $form_state->getValue('selected_langcode');
      foreach ($languages as $lang) {
        if ($langcode == $lang->getId()) {
          $language = $lang->getName();
        }
      }

      $get['position'] = $position;
      $get['language'] = $language;

    }
    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);

  }

  /**
   * Custom submit handler for next level.
   */
  public function next_level_submit(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    $textid = $form_state->getValue('text');

    $levels = $form_state->getValue('levels');
    $text_node = Node::load($textid);
    $machine_name = $text_node->field_machine_name->value;

    if ($levels == 1) {
      $get = $_GET;
      $textid = $form_state->getValue('text');

      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $chapter_selected = $position;

        // Check that the increment does not go beyond the total numvber of chapters
        // Find the number of top level terms.
        $topLevelTerms = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE bundle=:bundle AND parent_target_id = 0)", [':bundle' => $machine_name])->fetchAll();
        $topLevelTermsCount = count($topLevelTerms);

        // Increment the chapter.
        $chapter_selected += 1;

        // Check that the chapter does not exceed the topLevelCount.
        if ($chapter_selected > $topLevelTermsCount) {
          // Increment chapter number.
          $chapter_selected = 1;

        }

        // New position parameter.
        $position = $chapter_selected;
        // Attach position to the url.
        $get['position'] = $position;

      }

    }

    if ($levels == 2) {
      // $chapter_selected = 0;
      if (isset($_GET['position'])) {
        $position = $_GET['position'];

        $var = explode('.', $position);
        $chapter_selected = $var[0];
        $sloka_selected = $var[1];

        // Check that the increment does not go beyond the total numvber of chapters
        // Find the number of top level terms.
        $topLevelTerms = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE bundle=:bundle AND parent_target_id = 0)", [':bundle' => $machine_name])->fetchAll();
        $topLevelTermsCount = count($topLevelTerms);

        // Increment the chapter.
        $chapter_selected += 1;

        // Check that the chapter does not exceed the topLevelCount.
        if ($chapter_selected > $topLevelTermsCount) {
          // Increment chapter number.
          $chapter_selected = 1;

        }

        // New position parameter.
        $position = $chapter_selected . '.' . $sloka_selected;

        // Attach position to the url.
        $get['position'] = $position;

      }

    }

    if ($levels == 3) {

      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $var = explode('.', $position);

        $kanda_selected = $var[0];
        $sarga = $var[1];
        $sloka_selected = $var[2];

        // Increment sarga.
        $sarga += 1;

        // Find the kanda tid.
        $kanda_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :kanda_selected AND bundle = :textname", [':kanda_selected' => $kanda_selected, ':textname' => $machine_name])->fetchField();

        // Find the number of sarga for the given kanda.
        $sarga_count = calculate_sublevels($machine_name, $kanda_tid);

        if ($sarga > $sarga_count) {
          $sarga = 1;
        }

        $position = $kanda_selected . '.' . $sarga . '.' . $sloka_selected;
        $get['position'] = $position;

      }

    }

    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);

  }

  /**
   * Custom submit handler for previous level.
   */
  public function previous_level_submit(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    $textid = $form_state->getValue('text');

    $levels = $form_state->getValue('levels');
    $text_node = Node::load($textid);
    $machine_name = $text_node->field_machine_name->value;

    if ($levels == 1) {
      $get = $_GET;
      $textid = $form_state->getValue('text');

      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $chapter_selected = $position;

        // Check that the increment does not go beyond the total numvber of chapters
        // Find the number of top level terms.
        $topLevelTerms = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE bundle=:bundle AND parent_target_id = 0)", [':bundle' => $machine_name])->fetchAll();
        $topLevelTermsCount = count($topLevelTerms);

        // Increment the chapter.
        $chapter_selected -= 1;

        // Check that the chapter does not exceed the topLevelCount.
        if ($chapter_selected < 1) {
          // Increment chapter number.
          $chapter_selected = 1;

        }

        // New position parameter.
        $position = $chapter_selected;
        // Attach position to the url.
        $get['position'] = $position;

      }

    }

    if ($levels == 2) {
      // $chapter_selected = 0;
      if (isset($_GET['position'])) {
        $position = $_GET['position'];

        $var = explode('.', $position);
        $chapter_selected = $var[0];
        $sloka_selected = $var[1];

        // Check that the increment does not go beyond the total numvber of chapters
        // Find the number of top level terms.
        $topLevelTerms = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE bundle=:bundle AND parent_target_id = 0)", [':bundle' => $machine_name])->fetchAll();
        $topLevelTermsCount = count($topLevelTerms);
        // Decrement the chapter.
        $chapter_selected -= 1;

        // Check that the chapter don't fall below 0.
        if ($chapter_selected < 1) {
          // Decrement chapter number.
          $chapter_selected = 1;

        }

        // New position parameter.
        $position = $chapter_selected . '.' . $sloka_selected;

        // Attach position to the url.
        $get['position'] = $position;

      }

    }

    if ($levels == 3) {

      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $var = explode('.', $position);

        $kanda_selected = $var[0];
        $sarga = $var[1];
        $sloka_selected = $var[2];

        // Decrement sarga.
        $sarga -= 1;

        if ($sarga < 1) {
          $sarga = 1;
        }

        $position = $kanda_selected . '.' . $sarga . '.' . $sloka_selected;
        $get['position'] = $position;

      }

    }

    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);
  }

  /**
   * Custom submit handler for next sublevel.
   */
  public function next_sublevel_submit(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    $textid = $form_state->getValue('text');
    // Find the textname.
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
    $levels = $form_state->getValue('levels');

    if ($levels == 2) {

      if (isset($_GET['position'])) {
        $position = $_GET['position'];

        $var = explode('.', $position);
        $chapter_selected = $var[0];
        $sloka_selected = $var[1];

        // Check that the increment does not go beyond the total numvber of slokas
        // Find the number of slokas for each chapter.
        $chapter_tid = $form_state->getValue('chapters');
        $sub_level_count = calculate_sublevel_number($textname, $chapter_tid);

        // Increment the sloka.
        $sloka_selected += 1;

        // Check that the chapter does not exceed the topLevelCount.
        if ($sloka_selected > $sub_level_count) {
          $sloka_selected = 1;
        }

        // New position parameter.
        $position = $chapter_selected . '.' . $sloka_selected;

        // Attach position to the url.
        $get['position'] = $position;

      }
    }

    if ($levels == 3) {
      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $var = explode('.', $position);
        $kanda_selected = $var[0];
        $sarga = $var[1];
        $sloka_selected = $var[2];

        // Increment slokas.
        $sloka_selected += 1;
        $sarga_selected = $kanda_selected . '.' . $var[1];

        // Find the sarga tid.
        $sarga_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :sarga_selected AND bundle = :textname", [':sarga_selected' => $sarga_selected, ':textname' => $textname])->fetchField();

        // Find the total number of sloka for a given sarga.
        $sloka_count = calculate_sublevel_number($textname, $sarga_tid);

        if ($sloka_selected > $sloka_count) {
          $sloka_selected = 1;
        }

        $position = $kanda_selected . '.' . $sarga . '.' . $sloka_selected;
        $get['position'] = $position;

      }

    }
    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);
  }

  /**
   * Custom submit handler for previous sublevel.
   */
  public function previous_sublevel_submit(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    $textid = $form_state->getValue('text');
    // Find the textname.
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
    $levels = $form_state->getValue('levels');

    if ($levels == 2) {

      if (isset($_GET['position'])) {
        $position = $_GET['position'];

        $var = explode('.', $position);
        $chapter_selected = $var[0];
        $sloka_selected = $var[1];

        // Check that the increment does not go beyond the total numvber of slokas
        // Find the number of slokas for each chapter.
        $chapter_tid = $form_state->getValue('chapters');
        $sub_level_count = calculate_sublevel_number($textname, $chapter_tid);

        // Increment the sloka.
        $sloka_selected -= 1;

        // Check that the chapter does not exceed the topLevelCount.
        if ($sloka_selected < 1) {
          $sloka_selected = 1;
        }

        // New position parameter.
        $position = $chapter_selected . '.' . $sloka_selected;

        // Attach position to the url.
        $get['position'] = $position;

      }
    }
    if ($levels == 3) {
      if (isset($_GET['position'])) {
        $position = $_GET['position'];
        $var = explode('.', $position);
        $kanda_selected = $var[0];
        $sarga = $var[1];
        $sloka_selected = $var[2];

        // Deccrement slokas.
        $sloka_selected -= 1;

        if ($sloka_selected < 1) {
          $sloka_selected = 1;
        }

        $position = $kanda_selected . '.' . $sarga . '.' . $sloka_selected;
        $get['position'] = $position;

      }
    }
    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);
  }

  /**
   *
   */
  public function _ajax_chapter_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['chapter_formats'];

  }

  /**
   *
   */
  public function _ajax_kanda_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['kanda_formats'];

  }

  /**
   *
   */
  public function _ajax_sarga_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['sarga_formats'];

  }

}
