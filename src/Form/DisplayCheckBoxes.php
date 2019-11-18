<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;

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
    // $textid = 10405;
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
      if ($textname == 'gita') {
        $form['text_info']['fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Select the Chapter, Sloka '),
          '#description' => $this->t('Choose the content'),
        ];

        // Query for chapters.
        $chapters = [];
        $query = db_query("SELECT * FROM `taxonomy_term_field_data` WHERE name LIKE 'Chapter%' AND vid = :textname ORDER BY tid ASC", [':textname' => $textname])->fetchAll();

        foreach ($query as $key => $value) {
          $chapters[$value->tid] = $value->name;
        }

        $form['text_info']['fieldset']['chapters'] = [
          '#type' => 'select',
          '#title' => $this->t('Select Chapter'),
          '#required' => TRUE,
          '#options' => $chapters,
          '#default_value' => isset($form['text_info']['fieldset']['chapters']['widget']['#default_value']) ? $form['text_info']['fieldset']['chapters']['widget']['#default_value'] : NULL,
          '#ajax' => [
            'event' => 'change',
            'wrapper' => 'chapter-formats',
            'callback' => '::_ajax_chapter_callback',
          ],

        ];

        // Calculate number of sublevels for each chapter.
        $slokas = [];

        // GEt the chapter selected.
        if (!empty($form_state->getTriggeringElement())) {
          // Gives the tid of chapter.
          $chapter_selected_tid = $form_state->getUserInput()['chapters'];

        }
        if (!isset($chapter_selected_tid)) {
          $chapter_selected_tid = $form['text_info']['fieldset']['chapters']['widget']['#default_value'];
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
          $sub_level_count = calculate_sublevel_number('gita', $chapter_selected_tid);
          for ($i = 1; $i <= $sub_level_count; $i++) {
            $slokas[$i] = 'Sloka ' . $i;
          }

        }

        // print_r($chapter_selected);exit;
        $form['text_info']['fieldset']['chapter_formats']['slokas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select Sloka'),
          '#required' => TRUE,
          '#options' => $slokas,
          '#default_value' => isset($form['text_info']['fieldset']['slokas']['widget']['#default_value']) ? $form['text_info']['fieldset']['slokas']['widget']['#default_value'] : NULL,

        ];

        // Add a language field.
        $form['text_info']['fieldset']['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
        ];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Temporary Button'),
        ];

      }
      // TEXT name for ramayana, will be dealt later.
    }

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    $textid = $form_state->getValue('text');

    // Find the textname.
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();

    // Collect the chapter.
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
    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    // $url->setOption('query', [
    //   'position' => $position,
    //   'language' => $language,
    // ]);
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);

  }

  /**
   *
   */
  public function _ajax_chapter_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['chapter_formats'];

  }

}
