<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to Display Audio Play Options.
 *
 * @Block(
 *   id = "heritage_audio_play",
 *   admin_label = @Translation("Audio play options"),
 *   category = @Translation("Custom")
 * )
 */
class DisplayAudio extends BlockBase {

  /**
   *
   */
  public function build() {
    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\AudioPlay');
    $build = [];

    $build['form'] = $builtForm;
    $build['#cache']['max-age'] = 0;
    return $build;

  }

}
