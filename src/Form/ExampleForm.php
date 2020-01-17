<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 *
 */
class ExampleForm extends FormBase {

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
    return 'heritage_ui_example_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {
    $levels = 2;
    $level_labels = 'Chapter, Sloka';
    $level_labels_array = explode(',', $level_labels);

    $form['text_info'] = [
      '#type' => 'container',
      '#prefix' => '<div id="text-info">',
      '#suffix' => '</div>',
    ];
    // If an ajax call is made, set the appropriate variables.
    if (!empty($form_state->getTriggeringElement())) {
      \Drupal::logger('heritage_ui_ajax')->notice('Called first?');
      $triggeredBy = $form_state->getTriggeringElement()['#name'];
      $valueTriggered = $form_state->getTriggeringElement()['#value'];
      \Drupal::logger('heritage_ui_ajax')->notice('Triggered Value in form build by ' . $triggeredBy . ' is : ' . $valueTriggered);
      $keyTriggered = array_search ($triggeredBy, $level_labels_array);
      $levelToChange = $level_labels_array[$keyTriggered+1];
    }
    // Create the level fields
    for ($j = 0; $j < $levels; $j++) {
      $levelName = strtolower($level_labels_array[$j]);
      if ($j != 0) {
        if (isset($valueTriggered)) {
          if ($valueTriggered == 0) {
            $units = [1 => 1, 2 => 2, 3 => 3];
          }
          else {
            $units = [4 => 4, 5 => 5, 6 => 6];
          }
        }
        else $units = [1 => 1, 2 => 2, 3 => 3];
        $default_value = key($units);
        $div = strtolower($level_labels_array[$j]) . '-wrapper';
        $formDiv = strtolower($level_labels_array[$j]) . '_wrapper';
        $form['text_info'][$formDiv] = [
          '#type' => 'container',
          '#prefix' => '<div id="' .$div. '">',
          '#suffix' => '</div>',
        ]; 
        $form['text_info'][$formDiv][$levelName] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels_array[$j]),
          '#required' => TRUE,
          '#options' => $units,
          // '#value' => $default_value,
          '#default_value' => $default_value,
        ];
        \Drupal::logger('heritage_ui_ajax')->notice('Default Value in form build is: ' . $default_value);

      }
      else {
        $units = ['Chapter 1', 'Chapter 2'];
        $default_value = 0;
        $form['text_info'][$levelName] = [
          '#type' => 'select',
          '#title' => $this->t('Select ' . $level_labels_array[$j]),
          '#required' => TRUE,
          '#options' => $units,
          '#default_value' => $default_value,
          // '#value' => $default_value,
        ];
      }
      if ($j == 0){
        $wrapper = strtolower($level_labels_array[$j+1]) . '-wrapper';
        $form['text_info'][$levelName]['#ajax'] = [
          'event' => 'change',
          'wrapper' => 'navigationlevels',
          'callback' => '::submitFormAjax',
        ];
      }
      else {
        $form['text_info'][$levelName .'_wrapper'][$levelName]['#ajax'] = [
          'event' => 'change',
          'wrapper' => 'navigationlevels',
          'callback' => '::submitFormAjax',
        ];
      }
    }
    return $form;
  }
  
  /**
   *
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $levels = 2;
    $level_labels = 'Chapter, Sloka';
    $level_labels_array = explode(',', $level_labels);
    $triggeredBy = $form_state->getTriggeringElement()['#name'];
    if ($triggeredBy != $level_labels_array[$levels-1]) {
      \Drupal::logger('heritage_ui_ajax')->notice('Triggered By in submit:' . $triggeredBy);
      $response = $this->submitFormAjax2($form, $form_state);
    }
    else {
      \Drupal::logger('heritage_ui_ajax')->notice('Triggered By in submit:' . $triggeredBy);
      $response = $this->submitFormAjax2($form, $form_state);
    }
    return $response;
  }

  /**
   *
   */
  public function submitFormAjax2(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('heritage_ui_ajax')->notice('Called second');
    $values = $form_state->getValues();
    $levels = 2;
    $level_labels = 'Chapter, Sloka';
    $level_labels_array = explode(',', $level_labels);
    $params = [];
    $position_array = [];
    $position = '';
    foreach ($values as $key => $value) {
      for ($i = 0; $i < $levels; $i++) {
        if (strtolower($key) == strtolower($level_labels_array[$i])) {
          \Drupal::logger('heritage_ui_ajax')->notice('key is: ' . $key . ', Value is : ' . $value);
        }
      }
    }
    $build = [
      '#theme' => 'text_content',
      '#data'=> 'Test',
    ];
    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand('#textcontent', $build)
    );
    $response->addCommand(new ReplaceCommand(NULL, $form));
    return $response;
  }
   /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
