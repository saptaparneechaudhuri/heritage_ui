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
class SourcesMenuOrdered extends FormBase {

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
    $sources_menu_audio = [];
    $sources_menu_text = [];
    $sources = '';
    $selected_sourceids = [];
    $default_values = [];
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    // Get the user id.
    $userid = \Drupal::currentUser()->id();
    // Fetch all the available sources.
    $available_sources_audio = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid AND format = :format ORDER BY type", [':textid' => $textid, ':format' => 'audio'])->fetchAll();
    // the audios should come before the texts in the text boxes
    
   // $available_sources = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid ORDER BY format ASC", [':textid' => $textid])->fetchAll();

    


    if (count($available_sources_audio) > 0) {
      foreach ($available_sources_audio as $source) {
        if ($source->type == 'moolam' && $source->format == 'audio') {
          $moolid = $source->id;
        }
        $sources_menu_audio[$source->id] = $source->title;
      }
    }

    // Display Text Sources.
    $available_sources_text = db_query("SELECT * FROM `heritage_source_info`WHERE text_id = :textid AND format = :format ORDER BY id ASC", [':textid' => $textid, ':format' => 'text'])->fetchAll();

   
   


    if (count($available_sources_text) > 0) {
      foreach ($available_sources_text as $source) {
        if ($source->type == 'moolam' && $source->format == 'text') {
          $moolid = $source->id;
        }
        $sources_menu_text[$source->id] = $source->title;
      }
    }

    // See the format of the sources
    

    // Check the user id here.
    $userid = \Drupal::currentUser()->id();
    // Check the sources selected by the user in user_data_table.
    $user_selected_sources = db_query("SELECT * FROM `heritage_users_data` WHERE user_id = :userid AND text_id = :textid", [':userid' => $userid, ':textid' => $textid])->fetchAll();

    // print_r($user_selected_sources);exit;
    if (isset($user_selected_sources)) {
      foreach ($user_selected_sources as $s) {

        $selected_sourceids[] = $s->source_id;
      }
    }
    // print("<pre>");print_r($selected_sourceids); exit;.
    if (isset($_GET['source'])) {
      $selected_sources = explode(',', $_GET['source']);
      foreach ($selected_sources as $selected_source) {

        $field_array = explode('_', $selected_source);
        // Find out the format of the source.
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

    $form['sources_menu_audio'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Audios'),
      '#options' => $sources_menu_audio,
      '#default_value' => $selected_sourceids,
      '#multiple' => TRUE,
    ];
    $form['sources_menu_text'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Texts'),
      '#options' => $sources_menu_text,
      '#default_value' => $selected_sourceids,
      '#multiple' => TRUE,
    ];
   // $sources_menu = array_merge($sources_menu_texts, $sources_menu_audio);
    // $form['sources_menu'] = [
    //   '#type' => 'hidden',
    //  // '#title' => isset($title_flag) ? $this->t('Audios') : 'Texts',
    //   '#options' => $sources_menu,
    //   // '#default_value' => $selected_sourceids,
    //    '#multiple' => TRUE,
    // ];

    $form['user'] = [
      '#type' => 'hidden',
      '#value' => $userid,
    ];

    if (isset($userid) && $userid > 0) {
      $form['user_data'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Save Selected Sources'),
        '#required' => TRUE,
        '#default_value' => TRUE,
      ];

    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Display Content'),
    ];
    // print_r("About to exit");
    // print_r($sourceid);
    return $form;

  }

  /**
   * Attaches selected source to the url.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $get = $_GET;
    // print_r($get);exit;
    $textid = $form_state->getValue('text');
    $uid = $form_state->getValue('user');

    // Textname from textid.
    //  $element_default_value = $form['sources_menu']['#default_value'];
    // print_r($element);exit;
    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();

    $params = [];
    $audio_button_flag = [];

    // Select the sourceid from the selectes checkboxes.
    $var1 = $form_state->getValue('sources_menu_audio');

    $sources_audio = array_filter($var1);
    // print("<pre>");print_r($sources);exit;
    // $sourceid = [10586,10589];.
    $var2 = $form_state->getValue('sources_menu_text');
    $sources_text = array_filter($var2);

    $sources = array_merge($sources_audio,$sources_text);
    //print_r($sources);exit;
    
    $sourceid = select_sources($sources);

    // ***** USER PERSONALIZARION ************
    // Insert into the database for each sources selected.
    $db = \Drupal::database();

    // Check what sources the user has already selected.
    $sources_check = db_query("SELECT * FROM `heritage_users_data` WHERE user_id = :userid AND text_id = :textid", [':userid' => $uid, ':textid' => $textid])->fetchAll();

    // Variable to hold already present sources in database.
    $sources_present = [];
    $users_present = [];
    if (isset($sources_check)) {
      foreach ($sources_check as $s) {
        $sources_present[] = $s->source_id;
        $users_present[] = $s->user_id;
      }

    }

    // Compare fresh sources selected with sources already present.
    if (isset($sources_present) && $users_present) {
      foreach ($sources_present as $s) {
        if (in_array($uid, $users_present)  && !in_array($s, $sourceid)) {
          // print_r("not present");exit;.
          db_delete('heritage_users_data')
            ->condition('source_id', $s)
            ->condition('user_id', $uid)->execute();

        }

      }

    }

    foreach ($sourceid as $sid) {
      if (!in_array($sid, $sources_present)) {
        // Only insert for logged in users.
        if (isset($uid) && $uid > 0) {
          $db->insert('heritage_users_data')
            ->fields([
              'user_id' => $uid,
              'text_id' => $textid,
              'source_id' => $sid,
            ])->execute();

        }
        // $db->insert('heritage_users_data')
        //   ->fields([
        //     'user_id' => $uid,
        //     'text_id' => $textid,
        //     'source_id' => $sid,
        //   ])->execute();
      }

    }

    // *****************************************
    // Redirect to the custom page.
    $url = Url::fromRoute('heritage_ui.contentpage', ['textid' => $textid]);
    $field_name = [];

    foreach ($sourceid as $key => $value) {
      // $element_default_value[] = $value;
      // Check the source type.
      //  $source_type = db_query("SELECT type FROM `heritage_source_info` WHERE id = :sourceid AND text_id = :textid", [':sourceid' => $value, 'textid' => $textid])->fetchField();
      //  $audio_id = db_query("SELECT id FROM `heritage_source_info` WHERE parent_id = :sourceid AND text_id = :textid AND type = :type", [':sourceid' => $value, ':textid' => $textid, 'type' => $source_type])->fetchField();
      // Find out the format of the source before adding the field.
      $format = db_query("SELECT format FROM `heritage_source_info` WHERE id =:sourceid AND text_id = :textid", [':sourceid' => $value, ':textid' => $textid])->fetchField();

      // $field_name[] = 'field_' . 'gita_' . $value . '_text';
      //  $field_name[] = 'field_' . $textname . '_' . $value . '_text';.
      if (isset($sources_present)) {

        foreach ($sources_present as $s) {
          // $field_name[] = 'field_' . $textname . '_' . $s . '_' . $format;
        }

      }
      $field_name[] = 'field_' . $textname . '_' . $value . '_' . $format;

      // If ($audio_id > 0) {
      //  // $field_name[] = 'field_' . $textname . '_' . $audio_id . '_audio';
      // }.
    }

    // array_push($get['source'], $list)
    // If user has already selected sources.
    $list = implode(', ', $field_name);

    // $field_name = 'field_' . 'gita_' . $sourceid[0] . '_text';
    // $get['source'] = $field_name;.
    //  print("<pre>");print_r($field_name);exit;
    //   print_r($list);
    $get['source'] = $list;
    // print_r($get['source']);exit;
    // TODO, just attach the field name from here.
    $url->setOption('query', $get);
    // exit;.
    return $form_state->setRedirectUrl($url);

  }

}
                 
