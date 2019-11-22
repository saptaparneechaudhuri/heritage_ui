<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;

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

    // Get the textid from the current path

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

      //  $chapter_tid = $sloka_selected = NULL;
        $langcode = NULL;

        

       

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

        // If position parameter is present set the default value of the chapter field,
        // using the values of the position 
        // Set the default values of chapter and sloka using $_GET.
        if (isset($_GET['position'])) {
          $position = $_GET['position'];

          $var = explode('.', $position);
          $chapter_selected = $var[0];
          $sloka_selected = $var[1];
          
          // The tid of the chapter is gotten from chapter number
          $chapter_tid = db_query("SELECT entity_id FROM  `taxonomy_term__field_position` WHERE field_position_value = :chapter_selected AND bundle = :textname", [':chapter_selected' => $chapter_selected, ':textname' => $textname])->fetchField();
          // If the ajax is not triggered, set the chapter tid from position
          // This variable is used to display all the slokas of a chapter
           $chapter_selected_tid = $chapter_tid;

        }

        $form['text_info']['fieldset']['chapters'] = [
          '#type' => 'select',
          '#title' => $this->t('Select Chapter'),
          '#required' => TRUE,
          '#options' => $chapters,
         // '#default_value' => isset($form['text_info']['fieldset']['chapters']['widget']['#default_value']) ? $form['text_info']['fieldset']['chapters']['widget']['#default_value'] : $chapters[$chapter_tid],
          '#default_value' => isset($chapter_tid) ? $chapter_tid: NULL,

          '#ajax' => [
            'event' => 'change',
            'wrapper' => 'chapter-formats',
            'callback' => '::_ajax_chapter_callback',
          ],

        ];

        // Calculate number of sublevels for each chapter.
        $slokas = [];

        // Ajax triggers when a chapter is selected
        if (!empty($form_state->getTriggeringElement())) {
          // Gives the tid of chapter.
          $chapter_selected_tid = $form_state->getUserInput()['chapters'];

        }

        // if (!isset($chapter_selected_tid) && isset($_GET['position'])) {
        //   $chapter_selected_tid = 5227;
        // }
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

       // If position parameter is present in the url, set the default value for the sloka,
        // using the position parameter, else set the default as Sloka 1
        $form['text_info']['fieldset']['chapter_formats']['slokas'] = [
          '#type' => 'select',
          '#title' => $this->t('Select Sloka'),
          '#required' => TRUE,
          '#options' => $slokas,
          // '#default_value' => isset($form['text_info']['fieldset']['slokas']['widget']['#default_value']) ? $form['text_info']['fieldset']['slokas']['widget']['#default_value'] : $slokas[$sloka_selected],
          '#default_value' => isset($sloka_selected) ? $sloka_selected : NULL,

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
         // '#default_value' =>  isset($form['text_info']['fieldset']['selected_langcode']['widget']['#default_value']) ? $form['text_info']['fieldset']['selected_langcode']['widget']['#default_value'] : $langcode,
          '#default_value' => $langcode,

        // '#attributes' => ['onchange' => 'this.form.submit();'],
        //     '#ajax' => [
        //   'callback' => ':: submitForm',
        //   'event' => 'change',
        // ],
        ];
        $form['actions'] = [
          '#type' => 'submit',
          '#value' => 'Submit Chapter Position Language',
        ];

      }
      // Form for ramayana
      
    }

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
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
    // $form_state['rebuild'] = TRUE;
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
    // $response->addCommand(new RedirectCommand($url));
    // return $response;
  }

  // Public function _ajax_callback_setValues(array &$form, FormStateInterface $form_state) {
  // $response = new AjaxResponse();
  //     $get = $_GET;
  //   $textid = $form_state->getValue('text');
  // // Find the textname.
  //   $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
  // // Collect the chapter.
  //   $chapter_tid = $form_state->getValue('chapters');
  //   $sloka_number = $form_state->getValue('slokas');
  // // Get the chapter number from table taxonomy_term__field_position and see column field_positon_value.
  //   $chapter_number = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $chapter_tid, ':textname' => $textname])->fetchField();
  // $position = $chapter_number . '.' . $sloka_number;
  // // Default language.
  //   $language = 'devanagari';
  //   $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
  // // Collect the langcode.
  //   $langcode = $form_state->getValue('selected_langcode');
  //   // This loop is for printing the languages as English, Hindi, Bengali etc
  //   // Else it gets printed as en,dv,bn.
  //   foreach ($languages as $lang) {
  //     if ($langcode == $lang->getId()) {
  //       $language = $lang->getName();
  //     }
  //   }
  // // Attach position and language to url.
  //   $get['position'] = $position;
  //   $get['language'] = $language;
  // // $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
  //   // $url->setOption('query', [
  //   //   'position' => $position,
  //   //   'language' => $language,
  //   // ]);
  //   //$url->setOption('query', $get);
  // //  $url->setOption('query', ['test' => $test]);
  // $url = '/text/' . $textid . '/page?position=' . $position . '&' . 'language=' . $language;
  // $response->addCommand(new RedirectCommand($url));
  //   return $response;
  // }.

  /**
   *
   */
  public function _ajax_chapter_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['chapter_formats'];

  }

}
