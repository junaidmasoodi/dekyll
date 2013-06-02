<?php

/**
 * OG-Vocab content sync.
 */
class ContentSyncOgVocab extends ContentSyncBase {

  /**
   * Import YAML into OG-vocabs.
   *
   * @todo: We assume that sync has updated all OG-vocabs by the data in the
   * Jekyll file, and deleted any OG-vocab that doesn't match (i.e. complex
   * data).
   */
  public function import(EntityDrupalWrapper $wrapper, $yaml = array(), $text = '') {
    // Get all Vocabularies of the group.
    if (!$relations = og_vocab_relation_get_by_group('node', $this->gid)) {
      return;
    }

    $vids = array();
    foreach ($relations as $relation) {
      $vids[] = $relation->vid;
    }

    $vocabularies = taxonomy_vocabulary_load_multiple($vids);

    // The terms to save into the OG-vocab field.
    $terms = array();

    foreach ($vocabularies as $vocabulary) {
      $name = $vocabulary->name;
      if (empty($yaml[$name])) {
        // Property doesn't exist.
        continue;
      }

      $values = is_array($yaml[$name]) ? $yaml[$name] : array($yaml[$name]);

      foreach ($values as $value) {
        if (!$term = taxonomy_get_term_by_name($value, $vocabulary->machine_name)) {
          $values = array(
            'name' => $value,
            'vid' => $vocabulary->vid,
          );
          $term = entity_create('taxonomy_term', $values);
          taxonomy_term_save($term);
        }
      }

      $term = is_array($term) ? reset($term) : $term;
      $terms[] = $term;
    }

    // Add term to the OG-Vocab field.
    $wrapper->{OG_VOCAB_FIELD}->set($terms);
  }


  /**
   * Export OG-vocab
   */
  public function export(EntityDrupalWrapper $wrapper, &$yaml = array(), &$text = '') {
    if (!$terms = $wrapper->{OG_VOCAB_FIELD}->value()) {
      return;
    }

    // Re-group terms by their vocabulary.
    $values = array();
    foreach ($terms as $term) {
      $values[$term->vid][] = $term->name;
    }

    $vids = array_keys($values);

    $vocabularies = taxonomy_vocabulary_load_multiple($vids);

    // Get the related OG-vocabs, to know if the values are array or not.
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'og_vocab')
      ->propertyCondition('vid', $vids, 'IN')
      ->execute();

    $og_vocabs = entity_load('og_vocab', array_keys($result['og_vocab']));

    // Normalize the data that we want, the vocabulary ID as the key, and TRUE
    // if the value should be an array.
    $value_types = array();
    foreach ($og_vocabs as $og_vocab) {
      $value_types[$og_vocab->vid] = $og_vocab->settings['cardinality'] != 1;
    }

    foreach ($values as $vid => $vocab_terms) {
      // Populate the YAML.
      $vocab_name = $vocabularies[$vid]->name;

      if (!$value_types[$vid]) {
        $vocab_terms = $vocab_terms[0];
      }

      $yaml[$vocab_name] = $vocab_terms;
    }
  }
}