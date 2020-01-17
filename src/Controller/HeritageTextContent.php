<?php

namespace Drupal\heritage_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\node\Entity\Node;

/**
 *
 */
class HeritageTextContent extends ControllerBase {

  /**
   *
   */

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
  public function getContent($textid = NULL) {
    $form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\NavigationLevels');
    // $form2 = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\Metadata');
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);

    $textid = $arg[2];
    // Load the node.
    $node = Node::load($textid);
    // Find the number of levels for this textid.
    $level_labels = explode(',', $node->field_level_labels->value);
    $numLevels = count($level_labels);

    global $_SERVER;
    $result = [];
    $response = NULL;
    $build = [];
    $params['metadata'] = 0;

    $play_option = [];
    if (isset($_GET['source'])) {
      $list = $_GET['source'];
      $fields = explode(',', $list);
      foreach ($fields as $field_name) {
        $params[$field_name] = 1;
      }

      // $sources = [];
      // foreach($fields as $v){
      //   $var2 = explode('_', $v);
      //   $sources[] = $var2[2];
      // }
      // print("<pre>");print_r($sources);exit;
    }
    // See the play options.
    if (isset($_GET['play'])) {
      $list = $_GET['play'];
      $options = explode(',', $list);
      foreach ($options as $value) {
        $play_option[$value] = 1;
      }
    }
    if (isset($_GET['check'])) {
      $get = $_GET['check'];
    }
    else {
      $get = 'No GET Parameter';
    }

    // $params['position'] = '1';
    // Get the textid from the path and set the default position value accordingly
   
    for ($j = 0; $j < $numLevels; $j++) {
      if ($j == 0) {
        $position = '1';

      }
      else {
        $position = $position . '.' . '1';
      }
    }

    // print_r($position);exit;
    if (isset($position)) {
      $params['position'] = $position;

    }
    // print_r($params['position']);exit;.
    $response = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);
    if ($response) {
      $result = json_decode($response, TRUE);
      // Add the audio play option to the result array.
      if (isset($play_option)) {
        $result['play'] = $play_option;
      }

      // print("<pre>");print_r($result);exit;
    }

    // If (isset($_GET['metadata'])) {
    //   $metadata = $_GET['metadata'];
    //   $build = [];
    //   $param['metadata'] = $metadata;
    //   $sourceid = 10589;.
    // $response =  my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/source/' . $sourceid . '/status', 'GET', $param);
    // if ($response) {
    //      $result['metainfo'] = json_decode($response, TRUE);
    //       //print("<pre>");print_r($result);exit;
    // } // if response
    // } // if metadata
    // If you want to display block created from UI.
    $bid = 4;
    $block_content = BlockContent::load($bid);
    // $block_content =  \Drupal::entityManager()->getStorage('block')->load($bid);
    $rendered_block = \Drupal::entityTypeManager()
      ->getViewBuilder('block_content')
      ->view($block_content);

    // If you want to display plugin block.
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];
    $plugin_block = $block_manager->createInstance('heritage_audio_play', $config);
    $render_audio = $plugin_block->build();

    $plugin_block2 = $block_manager->createInstance('heritage_metadata', $config);
    $render_metadata = $plugin_block2->build();

    $build = [

      '#theme' => 'text_content',
      // '#theme' => 'test_content',
      '#data' => $result,
      // 'element-content' => $block_content,
    // '#context' => ['form' => $form2],
      '#audio_block' => $render_audio,
      '#metadata_block' => $render_metadata,

    ];

    return $build;
  }

  /**
   * Get the Name of the text.
   */
  public function getTitle($textid = NULL) {
    // Load the text name.
    $title = db_query("SELECT title FROM `node_field_data` WHERE nid = :textid AND type = :type", [':textid' => $textid, ':type' => 'heritage_text'])->fetchField();
    return $title;
  }

  /**
   * Ajax handler that insert the metadata into the div.
   * put static in front because this function is called statically in the form
   */
  public static function metadataDisplay(array $form, FormStateInterface $form_state) {

    // $selectedKey = $form_state->getValue('my_select');
    // $selectedValue = $form['my_select']['#options'][$selectedKey];
    $response_data = NULL;
    $result = [];
    $var = '';
    $params['metadata'] = $form_state->getValue('metadata');
    global $_SERVER;

    // Default mool shloka id. Hardcoded now, will be coded later.
    $sourceid = 10589;

    if (isset($_GET['source'])) {
      $list = $_GET['source'];
      $var = explode(',', $list);

      $sources = [];

      foreach ($var as $v) {
        $var2 = explode('_', $v);
        $sources[] = $var2[2];
      }

      // Call the rest response for metadata for each source.
      foreach ($sources as $sourceid) {
        // $var = '';
        $response_data = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/source/' . $sourceid . '/status', 'GET', $params);

        if ($response_data) {
          $result = json_decode($response_data, TRUE);
          // print("<pre>");print_r($result);exit;
          foreach ($result as $key => $value) {

            if ($key == 'metadata') {

              foreach ($value as $k => $v) {

                $var = $var . ' ' . $k . ':' . $v . '</br>';

              }

            }

          }

        }

      }

    }

    else {

      $response_data = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/source/' . $sourceid . '/status', 'GET', $params);

      if ($response_data) {
        $result = json_decode($response_data, TRUE);
        // print("<pre>");print_r($result);exit;
        foreach ($result as $key => $value) {

          if ($key == 'metadata') {
            // $var = $var . ' ' . $key;
            foreach ($value as $k => $v) {
              $var = $var . ' ' . $k . ':' . $v . '</br>';

            }

          }

        }
      }

    }

    $response = new AjaxResponse();
    // $selector = '#heritage-ui-metadata';
    $selector = '#metadata-area';

    $data = '<h2>Metadata</h2>' . ' ' . $var;
    $response->addCommand(new HtmlCommand($selector, $data));
    return $response;
  }

}
