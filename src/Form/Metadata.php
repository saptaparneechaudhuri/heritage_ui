<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;

/**
 *
 */
class Metadata extends FormBase {
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
  protected static $instanceId;

  /**
   * Class constructor.
   */
  public function __construct(CurrentPathStack $currPath, LinkGeneratorInterface $pathLink) {

    $this->currPath = $currPath;
    $this->pathLink = $pathLink;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(

      $container->get('path.current'),
      $container->get('link_generator')
    );

  }

  /**
   *
   */
  public function getFormId() {
    if (empty(self::$instanceId)) {
      self::$instanceId = 1;
    }
    else {
      self::$instanceId++;
    }
    return 'heritage_ui_metadata' . self::$instanceId;
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourceid = NULL) {
    // print_r($sourceid);exit;
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $metadata = 1;

    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];

    $form['sourceid'] = [
      '#type' => 'hidden',
      '#value' => $sourceid,
    ];

    $form['metadata'] = [
      '#type' => 'hidden',
      '#value' => $metadata,
    ];

    $form['display'] = [
      '#type' => 'button',
      '#value' => $this->t('More'),
      '#ajax' => [
        'callback' => '::get_metadata',
        'event' => 'click',
        // 'url' => Url::fromRoute('heritage_ui.metadata', ['sourceid' => $sourceid]),
      ],
    ];

    $form['#cache'] = [
      'max-age' => 0,
    ];

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Ajax Callback for the form - it opens the modal form.
   */
  public function get_metadata($form, $form_state) {
    $metadata = '';
    $values = $form_state->getValues();
    // \Drupal::logger('my_module')->notice('Value of source in Metadat submit is: ' . $values['sourceid']);
    $metadata_string = db_query("SELECT metadata FROM `heritage_field_meta_data` WHERE id = :id", [':id' => $values['sourceid']])->fetchField();
    $metadata_array = json_decode($metadata_string);
    foreach ($metadata_array as $key => $value) {
      $metadata = $metadata . $key . ': ' . $value . '<br>';
    }
    \Drupal::logger('my_module')->notice('Value of source in Metadat submit is: ' . $metadata);
    // Add an AJAX command to open a modal dialog with the metadata as the content.
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(t('More Information on this'), $metadata, ['width' => '555']));
    return $response;
  }

}
