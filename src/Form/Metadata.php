<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;

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
    return 'heritage_ui_metadata';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {

    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];

    $metadata = 1;

    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,

    ];

    $form['metadata'] = [
      '#type' => 'hidden',
      '#value' => $metadata,
    ];

    $form['display'] = [
      //'#title' => $this->t('More'),
      '#type' => 'button',
      '#value' => $this->t('More'),
       '#ajax' => [
        'callback' => 'Drupal\heritage_ui\Controller\HeritageTextContent::metadataDisplay',
        'effect' => 'slide',
        'event' => 'click',
      ],
    ];

    // $form['actions']['submit'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('More'),
    // ];

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // $get = $_GET;

    // $textid = $form_state->getValue('text');
    // $metadata = $form_state->getValue('metadata');

    // $url = Url::fromRoute('heritage_ui.contentpage', ['textid' => $textid]);
    // $get['metadata'] = $metadata;

    // $url->setOption('query', $get);
    // return $form_state->setRedirectUrl($url);

  }

}
