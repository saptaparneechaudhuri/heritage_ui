<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Url;

/**
 *
 */
class SourcesMenu extends FormBase {

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
    return 'heritage_ui_sources_menu';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {

    $sources_menu = [];
    $sources = '';
    $selected_sourceids = [];
    $default_values = [];
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    // Fetch all the available sources.
    $available_sources = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid ORDER BY language DESC", [':textid' => $textid])->fetchAll();
    
    if (count($available_sources) > 0) {
      foreach ($available_sources as $source) {
        if ($source->type == 'moolam' && $source->format == 'text') {
          $moolid = $source->id;
        }
        $sources_menu[$source->id] = $source->title;
      }
    }
    if (isset($_GET['source'])) {
      $selected_sources  = explode(',', $_GET['source']);
      foreach ($selected_sources as $selected_source) {
        $field_array = explode('_', $selected_source);
        $selected_sourceids[] = $field_array[2];
      }
    }
    else {
      $selected_sourceids[] = $moolid;
    }
    $form['#cache'] = ['max-age' => 0];
    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];
    $form['sources_menu'] = [
      '#type' => 'checkboxes',
      '#options' => $sources_menu,
      '#default_value' => $selected_sourceids,
      '#multiple' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Display Content'),
    ];
   // print_r("About to exit");
   // print_r($sourceid);
    return $form;

  }

  /**
   * Attaches selected source to the url
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    // print_r($get);exit;
    $textid = $form_state->getValue('text');
    // Textname from textid.
  //  $element_default_value = $form['sources_menu']['#default_value'];
   // print_r($element);exit;
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();

    $params = [];
    $audio_button_flag = [];

    // Select the sourceid from the selectes checkboxes.
    $var1 = $form_state->getValue('sources_menu');
    $sources = array_filter($var1);
    // print("<pre>");print_r($sourceid);exit;
    // $sourceid = [10586,10589];.
    $sourceid = select_sources($sources);
    //print_r("Inside the submit form");
   // print_r($sourceid);



    // Redirect to the custom page.
    $url = Url::fromRoute('heritage_ui.contentpage', ['textid' => $textid]);
    $field_name = [];

    foreach ($sourceid as $key => $value) {
   //   $element_default_value[] = $value;

      // Check the source type.
    //  $source_type = db_query("SELECT type FROM `heritage_source_info` WHERE id = :sourceid AND text_id = :textid", [':sourceid' => $value, 'textid' => $textid])->fetchField();
    //  $audio_id = db_query("SELECT id FROM `heritage_source_info` WHERE parent_id = :sourceid AND text_id = :textid AND type = :type", [':sourceid' => $value, ':textid' => $textid, 'type' => $source_type])->fetchField();

      // Find out the format of the source before adding the field.
      $format = db_query("SELECT format FROM `heritage_source_info` WHERE id =:sourceid AND text_id = :textid", [':sourceid' => $value, ':textid' => $textid])->fetchField();

      // $field_name[] = 'field_' . 'gita_' . $value . '_text';
      //  $field_name[] = 'field_' . $textname . '_' . $value . '_text';.
      $field_name[] = 'field_' . $textname . '_' . $value . '_' . $format;

      // if ($audio_id > 0) {
      //  // $field_name[] = 'field_' . $textname . '_' . $audio_id . '_audio';
      // }

    }

    //print_r($element);exit;

    // Comma in the array
    // comma in the array.
    $list = implode(', ', $field_name);

    // $field_name = 'field_' . 'gita_' . $sourceid[0] . '_text';
    // $get['source'] = $field_name;.
  //  print("<pre>");print_r($field_name);exit;
    $get['source'] = $list;
    

    // TODO, just attach the field name from here.
    $url->setOption('query', $get);
   // exit;

    return $form_state->setRedirectUrl($url);

  }

}
