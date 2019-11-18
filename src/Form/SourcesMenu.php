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
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {

    $sources_menu = [];
    $sources = '';

    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    // print_r($arg);exit;
    // Fetch all the available sources.
    $available_sources = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid ORDER BY language DESC", [':textid' => $textid])->fetchAll();

    if (count($available_sources) > 0) {
      foreach ($available_sources as $s) {
        $sources_menu[$s->id] = $s->title;
      }

    }
    // print_r($sources_menu);exit;
    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];

    $form['sources_menu'] = [
      '#title' => t('Sources Menu'),
      '#type' => 'checkboxes',
      // '#description' => t('Select the Sources.'),
      '#options' => $sources_menu,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Display Content'),
    ];

    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    // print_r($get);exit;
    $textid = $form_state->getValue('text');
    $params = [];

    // Select the sourceid from the selectes checkboxes.
    $var1 = $form_state->getValue('sources_menu');
    $sources = array_filter($var1);
    // print("<pre>");print_r($sourceid);exit;
    // $sourceid = [10586,10589];.
    $sourceid = select_sources($sources);

    // Redirect to the custom page.
    $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $textid]);
    $field_name = [];

    foreach ($sourceid as $key => $value) {
      $field_name[] = 'field_' . 'gita_' . $value . '_text';
    }

    // Comma in the array
    // comma in the array.
    $list = implode(', ', $field_name);

    // $field_name = 'field_' . 'gita_' . $sourceid[0] . '_text';
    // $get['source'] = $field_name;
    $get['source'] = $list;

    // TODO, just attach the field name from here.
    $url->setOption('query', $get);

    return $form_state->setRedirectUrl($url);

  }

}
