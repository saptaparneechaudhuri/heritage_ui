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
class AudioMenu extends FormBase {

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
    return 'heritage_ui_audio_menu';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {
    $audio_menu = [];
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $selected_audio_ids = [];

    // Get the user id.
    $userid = \Drupal::currentUser()->id();

    $available_sources = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid AND format = :format ORDER BY language DESC", [':textid' => $textid, ':format' => 'audio'])->fetchAll();

    if (count($available_sources) > 0) {
      foreach ($available_sources as $source) {

        $audio_menu[$source->id] = $source->title;

      }
    }

    if (isset($_GET['source'])) {
      $selected_audios = explode(',', $_GET['source']);
      foreach ($selected_audios as $selected_audio) {
        
        $field_array = explode('_', $selected_audio);
        $selected_audio_ids[] = $field_array[2];

      }

    }
   // print_r($selected_audio_ids);

    // TODO user personalization.
    $form['#cache'] = ['max-age' => 0];

    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];

    $form['audio_menu'] = [
      '#type' => 'checkboxes',
      '#options' => $audio_menu,
      '#default_value' => $selected_audio_ids,
      '#multiple' => TRUE,
    ];

    $form['user'] = [
      '#type' => 'hidden',
      '#value' => $userid,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Display Audio'),
    ];

    return $form;
  }

  /**
   * Attaches selected audio to the url.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $get = $GET;
    $textid = $form_state->getValue('text');
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();

    // Select the sourceid from the selectes checkboxes.
    $var1 = $form_state->getValue('audio_menu');
    $sources = array_filter($var1);
    $sourceid = select_sources($sources);

    // Redirect to the custom page.
    $url = Url::fromRoute('heritage_ui.contentpage', ['textid' => $textid]);
    $field_name = [];

    foreach ($sourceid as $key => $value) {
      $field_name[] = 'field_' . $textname . '_' . $value . '_audio';

    }

    // array_push($get['source'], $list)
    // TODO: get the existing field name from url if any and append it to the new list
    // then add the list to $get['source'].
 
    
      $list = implode(', ', $field_name);
     // print_r($list);
      if(isset($_GET['source'])) {
        $get['source'] = $get['source'] . ',' . $list;
      }
      else{
      $get['source'] = $list;


      }

    
     // print_r($get['source']);exit;

    $url->setOption('query', $get);
    return $form_state->setRedirectUrl($url);
    //exit;

  }

}
