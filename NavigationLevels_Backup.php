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
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ChangedCommand;

/**
 *
 */
class NavigationLevels extends FormBase {

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
    return 'heritage_ui_navigation_levels';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {
    // Get the textid from the current path.
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $levelToChange = [];
    $newParents = [];
    $textid = $arg[2];
    $form['#prefix'] = '<div id="navigationlevels">';
    $form['#suffix'] = '</div>';
    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];
    if (isset($textid) && $textid > 0) {
      // Find the textname.
      $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
      $form['textname'] = [
        '#type' => 'hidden',
        '#value' => $textname,
      ];
      // Query to find the number of levels of a textid.
      $levels = db_query("SELECT field_levels_value FROM `node__field_levels` WHERE entity_id = :textid and bundle = :bundle",
                [
                  ':textid' => $textid,
                  ':bundle' => 'heritage_text'
                ])->fetchField();
      $form['levels'] = [
        '#type' => 'hidden',
        '#value' => $levels,
      ];

      $level_labels = db_query("SELECT field_level_labels_value FROM `node__field_level_labels` WHERE entity_id = :textid and bundle = :bundle",
        [
          ':textid' => $textid,
          ':bundle' => 'heritage_text'
        ])->fetchField();
      $form['level_labels'] = [
        '#type' => 'hidden',
        '#value' => $level_labels,
      ];
      $level_labels_array = explode(',', $level_labels);

      $form['text_info'] = [
        '#type' => 'container',
        '#prefix' => '<div id="text-info">',
        '#suffix' => '</div>',
      ];
      $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
      if (isset($_GET['language'])) {
        $language = $_GET['language'];
      }
      else {
        $language = 'Devanagari';
      }
      foreach ($languages as $lang) {
        if ($language == $lang->getName()) {
          $langcode = $lang->getId();
        }
      }
      // Add a language field.
      $form['text_info']['selected_langcode'] = [
        '#type' => 'language_select',
        '#title' => $this->t('Language'),
        '#required' => TRUE,
        '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
        '#default_value' => $langcode,
      ];

      // If an ajax call is made, set the appropriate variables.
      if (!empty($form_state->getTriggeringElement())) {
        $triggeredBy = $form_state->getTriggeringElement()['#name'];
        if (isset($_POST['button_clicked'])) {
          $triggeredByArray = explode("_", $_POST['button_clicked']);
          // If the navigation buttons are clicked, do the following.
          if (isset($triggeredByArray[2]) && $triggeredByArray[2] == 'navigation') {
            $ajaxCalledBy = $triggeredByArray[2];
            $navLevleChange = $triggeredByArray[0];
            $navLevelChangeDirection = $triggeredByArray[1];
            $currentValue = $form_state->getValue($navLevleChange);
            $position = db_query("SELECT field_position_value FROM `taxonomy_term__field_position` WHERE entity_id = :tid", [':tid' =>$currentValue])->fetchField();
            \Drupal::logger('current_value')->notice('Current Value: ' . $currentValue . ', Position: ' . $position);
            if ($navLevelChangeDirection == 'next') {
              // $default_value = $currentValue + 1;
              $newposition = $this->_get_next_level($textname, $position, 0);
              $newposition_tid = $newposition; 
              for($l = $levels -1; $l >= 0; $l--) {
                $newParents[$l] = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' =>$newposition_tid])->fetchField();
                $newposition_tid = $newParents[$l];
              }
              // $newParents = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' =>$newposition])->fetchField();
            }
            if ($navLevelChangeDirection == 'prev') {
              // $default_value = $currentValue + 1;
              $newposition = $this->_get_prev_level($textname, $position, 0);
              $newposition_tid = $newposition; 
              for($l = $levels -1; $l >= 0; $l--) {
                $newParents[$l] = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' =>$newposition_tid])->fetchField();
                $newposition_tid = $newParents[$l];
              }
            }
            \Drupal::logger('new_parents_array')->notice('<pre><code>' . print_r($newParents, TRUE) . '</code></pre>');

          }
          else {
            $valueTriggered = $form_state->getTriggeringElement()['#value'];
            $keyTriggered = array_search (ucfirst($triggeredBy), $level_labels_array);
            if ($keyTriggered != $levels - 1) {
              for ($i = $keyTriggered+1; $i < $levels; $i++) {
                $levelToChange[$i] = $level_labels_array[$i];
              }
            }
            else {
              $levelToChange[] = $level_labels_array[$keyTriggered];
            }
            \Drupal::logger('in_form_build_levels_to_change')->notice('<pre><code>' . print_r($levelToChange, TRUE) . '</code></pre>');
          }
        }
      }
      
      // Create the level fields
      for ($j = 0; $j < $levels; $j++) {
        $levelName = strtolower($level_labels_array[$j]);
        $units = [];
        // if ($j != 0) {
          // If the drop down values are changed, get the no.of sublevels.
          // if (isset($levelToChange) && $level_labels_array[$j] == $levelToChange && $j != ($levels - 1)) {
          if (count($levelToChange) > 0 && array_search($level_labels_array[$j], $levelToChange)) {
            if ($j == ($levels - 1)) {
              if ($keyTriggered == $j) {
                $id = $form_state->getValue(strtolower($level_labels_array[$j-1]));
                $units = $this->get_sub_levels($id, $textname);
                $default_value = $valueTriggered;
              }
              else {
                // $units = $this->get_sub_levels($valueTriggered, $textname);
                if ($keyTriggered != $j-1) {
                  $id = $form_state->getValue(strtolower($level_labels_array[$j-1]));
                   \Drupal::logger('in_form_build_key_triggered_if')->notice('<pre><code>' . print_r($id, TRUE) . '</code></pre>');
                }
                else {
                  $id = $valueTriggered;
                  \Drupal::logger('in_form_build_key_triggered_else')->notice('<pre><code>' . print_r($id, TRUE) . '</code></pre>');
                }
                $units = $this->get_sub_levels($id, $textname);
                $first_element = key($units);
                $default_value = $first_element;
              }
            }
            else {
              if ($j != 0) {
                $id = $form_state->getValue(strtolower($level_labels_array[$j-1]));
                $units = $this->get_sub_levels($id, $textname);
                $first_element = key($units);
                $default_value = $first_element;
              }
              else {
                $units = $this->get_sub_levels(0, $textname);
                $default_value = $valueTriggered;
              }
            }
          }
          else {
            // If there are no ajax calls, load the default values to form elements.
            if (empty($form_state->getTriggeringElement())) {
              if ($j !=0 ) {
                $id = $form_state->getValue(strtolower($level_labels_array[$j-1]));
              }
              else {
                $id = 0;
              }
            }
            // Otherwise get the changed value and set the form fields appropriately.
            else {
              if ($j != 0) {
                $id = $form_state->getValue(strtolower($level_labels_array[$j-1]));
              }
              else {
                $id = 0;
              }
            }
            $units = $this->get_sub_levels($id, $textname);
            $first_element = key($units);
            if (isset($ajaxCalledBy) && $ajaxCalledBy == 'navigation') {
              if ($navLevleChange == $levelName) {
                if ($j != 0 && $newParents[$j-1] != 0) {
                  $units = $this->get_sub_levels($newParents[$j], $textname);
                }
                $default_value = $newposition;  
              }
              else {
                if ($j != ($levels -1)) {
                  // $default_value = $form_state->getValue(strtolower($level_labels_array[$j]));
                  if ($j != 0) {
                    $units = $this->get_sub_levels($newParents[$j], $textname);
                  }
                  else {
                    $units = $this->get_sub_levels(0, $textname);
                  }
                  $default_value = $newParents[$j+1];
                }
                else {
                  $default_value = $first_element;
                }
              }
            }
            else if (count($levelToChange) == 0) {
              $default_value = $first_element;
            } 
            else {
              $default_value = $form_state->getValue(strtolower($level_labels_array[$j]));
            }
          }
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
            '#id' => 'edit-'.$levelName,
            // '#value' => $default_value,
            '#default_value' => $default_value,
          ];
          $form_state->setValue($levelName , $default_value);
          $form['text_info'][$levelName .'_wrapper'][$levelName]['#ajax'] = [
            'event' => 'change',
            'wrapper' => 'navigationlevels',
            'callback' => '::submitFormAjax',
          ];
      }
    }
    // Navigation Buttons.
    $drupalSettings = 0;
    $form['navbuttons']['#prefix'] = '<div id="navigationButtons">';
    $form['navbuttons']['#suffix'] = '</div>';
    for ($l = $levels; $l > 0; $l--) {
      $levelName = strtolower($level_labels_array[$l-1]);
      $textOnButton = '';
      for ($k = 1; $k <= $l; $k++) {
        $textOnButton = $textOnButton . '&lt;';
      }
      $label = $levelName . '_prev_navigation';
      
      $wrapper = $levelName . '_wrapper';
      $form['navbuttons'][$label] = [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'button',
          'value' => $this->t($textOnButton),
          'class' => 'button',
          'name' => $this->t($label),
          'id' => $this->t($label),
          'wrapper' => 'navigationlevels',
        ], 
        '#attached' => [
          'library' => [
            'heritage_ui/heritage_ui_library',
          ],
        ],
      ];
      $form[$label]['#attached']['drupalSettings']['nav'][$drupalSettings] = $label;
      $form[$label]['#attached']['drupalSettings']['navLevel'][$drupalSettings] = $levelName;
      $drupalSettings++;
    }
    for ($l = 1; $l <= $levels; $l++) {
      $levelName = strtolower($level_labels_array[$l-1]);
      $textOnButton = '';
      for ($k = 1; $k <= $l; $k++) {
        $textOnButton = $textOnButton . '&gt;';
      }
      $label = $levelName . '_next_navigation';
      
      $wrapper = $levelName . '_wrapper';
      $form['navbuttons'][$label] = [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'button',
          'value' => $this->t($textOnButton),
          'class' => 'button',
          'name' => $this->t($label),
          'id' => $this->t($label),
          'wrapper' => 'navigationlevels',
        ], 
        '#attached' => [
          'library' => [
            'heritage_ui/heritage_ui_library',
          ],
        ],
      ];
      $form[$label]['#attached']['drupalSettings']['nav'][$drupalSettings] = $label;
      $form[$label]['#attached']['drupalSettings']['navLevel'][$drupalSettings] = $levelName;
      $drupalSettings++;
    }
    $form['navbuttons']['clicked'] = [
      '#type' => 'hidden',
      '#default_value' => 'default',
      '#name' => 'button_clicked',
      '#attributes' => [
        'id' => 'button-clicked',
      ]
    ];
    return $form;
  }
  
  /**
   *
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $level_labels = $values['level_labels'];
    $level_labels_array = explode(",", $level_labels);
    $levels = count($level_labels_array);
    $triggeredBy = $_POST['button_clicked'];
    if ($triggeredBy == 'default') {
      $triggeredBy = $form_state->getTriggeringElement()['#name'];
      if ($triggeredBy != $level_labels_array[$levels-1]) {
        $response = $this->submitFormAjax2($form, $form_state);
      }
      else {
        $response = $this->submitFormAjax2($form, $form_state);
      }
    }
    else {
      $response = $this->submitFormAjax2($form, $form_state);
    }
    return $response;
  }


  /**
   *
   */
  public function submitFormAjax2(array &$form, FormStateInterface $form_state) {
    $newValue = [];
    $levelToChange = [];
    $values = $form_state->getValues();
    $level_labels = $values['level_labels'];
    $level_labels_array = explode(",", $level_labels);
    $levels = count($level_labels_array);
    $triggeredBy = $_POST['button_clicked'];
    $triggeredByArray = explode("_", $triggeredBy);
    $params = [];
    $position_array = [];
    $position = '';
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $textname = $values['textname'];
    $langcode = $values['selected_langcode'];
    $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
    foreach ($languages as $lang) {
      if ($langcode == $lang->getId()) {
        $language = $lang->getName();
      }
    }
    foreach ($values as $key => $value) {
      for ($i = 0; $i < $levels; $i++) {
        if (strtolower($key) == strtolower($level_labels_array[$i])) {
         \Drupal::logger('in_submit')->notice('Key is: ' . $key . ' Value is: ' . $value);
          if (isset($triggeredByArray[1]) && isset($triggeredByArray[2]) && $triggeredByArray[2] == 'navigation') {
            // if (strtolower($key) == $triggeredByArray[0]) {
              // if ($triggeredByArray[1] == 'next') {
                $newValue[] = $value;
                $levelToChange[] = '#edit-' . $key;
              // }
             /*  else {
                $newValue[]  = $value;
                $levelToChange[] = '#edit-' . $triggeredByArray[0];
              } */
            // }
          }
          $position_value = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $value, ':textname' => $textname])->fetchField();
          $position_array[] = $position_value;
        }
      }
    }
    \Drupal::logger('in_submit_position')->notice('<pre><code>' . print_r($position_array, TRUE) . '</code></pre>');
    $position = $position_array[$levels - 1];
    $params['position'] = $position;
    $params['language'] = $language;
    $params['metadata'] = 0;
    if (isset($_GET['source'])) {
      $list = $_GET['source'];
      $fields = explode(',', $list);
      foreach ($fields as $field_name) {
        $params[$field_name] = 1;
      }
    }
    else {
      $moolshloka_source_id = db_query("SELECT id FROM `heritage_source_info` WHERE text_id = :textid AND type = 'moolam' AND format = 'text'", [':textid' => $textid])->fetchField();
      $field_name = 'field_' . $textname . '_' . $moolshloka_source_id . '_text';
      $params[$field_name] = 1;
    }
    \Drupal::logger('in_submit')->notice('Position is: ' . $params['position']);
    if (isset($params[$field_name]) && isset($params['position']) && isset($params['language'])) {
      $result = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);
    } 
    $result_json = json_decode($result, TRUE);
    $build = [
      '#theme' => 'text_content',
      '#data'=> $result_json,
    ];
    // $form_state->setRebuild();
    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand('#textcontent', $build)
    );
    // $response->addCommand(new ReplaceCommand(NULL, $form));
    if (count($newValue) > 0) {
      // $form_state->setRebuild(TRUE);
      $response->addCommand(new ReplaceCommand(NULL, $form));
      for ($i = 0; $i < count($newValue); $i++) { 
        $tmp[0] = $newValue[$i];
        $response->addCommand(new InvokeCommand($levelToChange[$i], 'val' , $tmp));
      }
    }
    else {
      $response->addCommand(new ReplaceCommand(NULL, $form));
    }
    return $response;
  }

  /**
   *
   */
  public function _get_prev_level($textname, $position, $recursive) {
    $newposition = '';
    $nextTid = 0;
    $position_array = explode(".", $position);
    $levels = count($position_array);
    $currentTid = db_query("SELECT entity_id FROM `taxonomy_term__field_position` WHERE bundle = :bundle AND field_position_value = :position", [':bundle' => $textname, ':position' => $position])->fetchField();
    for($i = $levels-1; $i >= 0; $i--) {
      if ($recursive == 1 && $currentTid != null) {
        return $currentTid;
        break;
      }
      else if ($currentTid != null) {
          $currentParent = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' => $currentTid])->fetchField(); 
          $nextPosition = $position_array[$i] - 1;
          $nextTid = db_query("SELECT entity_id FROM `taxonomy_term__field_index` WHERE bundle = :bundle AND field_index_value = :nextPosition AND entity_id IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent)", 
          [
            ':bundle' => $textname,
            ':nextPosition' => $nextPosition,
            ':parent' => $currentParent,
          ])->fetchField(); 
          if ($nextTid > 0 ) {
            $position_array[$i] = $nextPosition;
            return $nextTid;
            break;
          }
          else {
            $position_array[$i] = 1;
            if ($i != 0) {
              $position_array[$i-1] = $position_array[$i-1] - 1;
            }
            for ($j = 0; $j < count($position_array); $j++) {
              if ($j != (count($position_array) - 1)) {
                $newposition = $newposition . $position_array[$j] . '.';
              }
              else {
                $newposition = $newposition . $position_array[$j];
              }
            }
            $nextTid = $this->_get_prev_level($textname, $newposition, 1);
            if (isset($nextTid) && $nextTid > 0 && $nextTid != null){
              return $nextTid;
              break;
            }
          }
      }
      else {
        $position_array[$i] = 1;
        if ($i != 0) {
          $position_array[$i-1] = 1;
        }
        for ($j = 0; $j < count($position_array); $j++) {
          if ($j != (count($position_array) - 1)) {
            $newposition = $newposition . $position_array[$j] . '.';
          }
          else {
            $newposition = $newposition . $position_array[$j];
          }
        }
        $nextTid = $this->_get_prev_level($textname, $newposition, 1);
        if ($nextTid != null){
          return $nextTid;
          break;
        }
      }
    }
  }

  /**
   *
   */
  public function _get_next_level($textname, $position, $recursive) {
    $newposition = '';
    $nextTid = 0;
    $position_array = explode(".", $position);
    $levels = count($position_array);
    $currentTid = db_query("SELECT entity_id FROM `taxonomy_term__field_position` WHERE bundle = :bundle AND field_position_value = :position", [':bundle' => $textname, ':position' => $position])->fetchField();
    for($i = $levels-1; $i >= 0; $i--) {
      if ($recursive == 1 && $currentTid != null) {
        \Drupal::logger('current_position_array')->notice('Inside recursive Current tid for position ' . $position . ' is: ' . $currentTid . ' and recursive is ' . $recursive);
        return $currentTid;
        break;
      }
      else if ($currentTid != null) {
          \Drupal::logger('get_next_level')->notice('Value of i is: ' . $i);
          \Drupal::logger('get_next_level')->notice('Position: ' . $position);
          $currentParent = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' => $currentTid])->fetchField(); 
          $nextPosition = $position_array[$i] + 1;
          $nextTid = db_query("SELECT entity_id FROM `taxonomy_term__field_index` WHERE bundle = :bundle AND field_index_value = :nextPosition AND entity_id IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent)", 
          [
            ':bundle' => $textname,
            ':nextPosition' => $nextPosition,
            ':parent' => $currentParent,
          ])->fetchField(); 
          if ($nextTid > 0 ) {
            $position_array[$i] = $nextPosition;
            \Drupal::logger('get_next_level_updated')->notice('<pre><code>' . print_r($position_array, TRUE) . '</code></pre>');
            \Drupal::logger('get_next_level')->notice('Next tid is: ' . $nextTid);
            return $nextTid;
            break;
          }
          else {
            $position_array[$i] = 1;
            if ($i != 0) {
              $position_array[$i-1] = $position_array[$i-1] + 1;
            }
            for ($j = 0; $j < count($position_array); $j++) {
              if ($j != (count($position_array) - 1)) {
                $newposition = $newposition . $position_array[$j] . '.';
              }
              else {
                $newposition = $newposition . $position_array[$j];
              }
            }
            \Drupal::logger('get_next_level')->notice('New Position: ' . $newposition);
            $nextTid = $this->_get_next_level($textname, $newposition, 1);
            if (isset($nextTid) && $nextTid > 0 && $nextTid != null){
              return $nextTid;
              break;
            }
          }
      }
      else {
        \Drupal::logger('get_next_level')->notice('Value of i in else is: ' . $i);
        $position_array[$i] = 1;
        if ($i != 0) {
          $position_array[$i-1] =  1;
        }
        for ($j = 0; $j < count($position_array); $j++) {
          if ($j != (count($position_array) - 1)) {
            $newposition = $newposition . $position_array[$j] . '.';
          }
          else {
            $newposition = $newposition . $position_array[$j];
          }
        }
        \Drupal::logger('new_position_value_to_check')->notice('New Position in no current id: ' . $newposition);
        $nextTid = $this->_get_next_level($textname, $newposition, 1);
        if ($nextTid != null){
          return $nextTid;
          break;
        }
      }
    }
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   *
   */
  public function get_sub_levels($tid, $textname) {
    $subLevels = [];
    if ($tid != 0) {
      $unitsInThisLevel = db_query("SELECT name, tid FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :tid) ORDER BY tid ASC",
      [
        ':tid' => $tid,
      ])->fetchAll();
    }
    else {
      $unitsInThisLevel = db_query("SELECT name, tid FROM `taxonomy_term_field_data` WHERE vid = :textname AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :tid) ORDER BY tid ASC",
      [
        ':textname' => $textname,
        ':tid' => 0,
      ])->fetchAll();
    }
    foreach ($unitsInThisLevel as $value) {
      $subLevels[$value->tid] = $value->name;
    }
    
    return $subLevels;
  }

}
