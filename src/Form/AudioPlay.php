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
class AudioPlay extends FormBase {


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
   *
   */
  public function getFormId() {
    return 'heritage_ui_audio_play';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {

    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $play_option = [];

    if (isset($_GET['play'])) {
      $var = $_GET['play'];
      $var2 = explode(',', $var);

      foreach ($var2 as $key => $value) {
        $play_option[$key] = $value;
      }
    }

    $audio = [
      'autoplay' => $this->t('Auto Play'),
      'continuousplay' => $this->t('Continuous Play'),
    ];

    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];

    $form['audio_options'] = [
      '#type' => 'checkboxes',
      '#options' => $audio,
      '#default_value' => isset($play_option) ? $play_option : NULL,
      '#attributes' => ['onchange' => 'this.form.submit();'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Play'),
      // Hides submit button because auto submit is used
    '#attributes' => [
      'style' => ['display: none;'],
    ],
    ];

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $get = $_GET;

    $textid = $form_state->getValue('text');
    $var1 = $form_state->getValue('audio_options');
    $options = array_filter($var1);
    $play_option = select_sources($options);

    $url = Url::fromRoute('heritage_ui.contentpage', ['textid' => $textid]);
    $play = [];

    foreach ($options as $key => $value) {
      $play[] = $value;
    }
    // print_r($play);exit;
    $list = implode(',', $play);

    $get['play'] = $list;

    $url->setOption('query', $get);
    return $form_state->setRedirectUrl($url);

  }

}
