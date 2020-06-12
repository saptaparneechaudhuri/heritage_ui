<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;


/**
 * Provides a block to Display Audios of a Heritage Text, .
 *
 * @Block(
 *   id = "heritage_audio_menu",
 *   admin_label = @Translation("Audios of Heritage Text in Menu"),
 *   category = @Translation("Custom")
 * )
 */


class DisplayAllAudio extends BlockBase {



    /**
   * {@inheritdoc}
   */
  

  public function build() {
    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\AudioMenu');
    $build = [];

    $build['#cache']['max-age'] = 0;
    $build['form'] = $builtForm;
    return $build;





  }













}

