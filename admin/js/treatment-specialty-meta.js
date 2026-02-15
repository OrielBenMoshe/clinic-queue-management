/**
 * חיפוש ובחירה מרובה בהתמחויות – ממשק אדמין
 *
 * @package ClinicQueue
 */

(function ($) {
  'use strict';

  function init() {
    const $container = $('.clinic-queue-specialty-checklist').closest('.form-field, tr');
    if (!$container.length) {
      return;
    }

    const $search = $container.find('.clinic-queue-specialty-search');
    const $labels = $container.find('.clinic-queue-specialty-label');
    const $selectAll = $container.find('.clinic-queue-specialty-select-all');
    const $deselectAll = $container.find('.clinic-queue-specialty-deselect-all');

    if (!$search.length || !$labels.length) {
      return;
    }

    // חיפוש
    $search.on('input', function () {
      const q = $(this).val().trim().toLowerCase();
      $labels.each(function () {
        const $label = $(this);
        const text = $label.text().trim().toLowerCase();
        const matches = !q || text.indexOf(q) !== -1;
        $label.toggleClass('clinic-queue-hidden', !matches);
      });
    });

    // בחר הכל
    $selectAll.on('click', function () {
      $labels.filter(':visible').find('input[type="checkbox"]').prop('checked', true);
    });

    // בטל הכל
    $deselectAll.on('click', function () {
      $labels.find('input[type="checkbox"]').prop('checked', false);
    });
  }

  $(function () {
    init();
  });
})(jQuery);
