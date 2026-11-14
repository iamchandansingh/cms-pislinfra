(function ($) {
  'use strict';

  // Single Media Picker
  $(document).on('click', '.pisl-media-select', function (e) {
    e.preventDefault();
    const $picker = $(this).closest('.pisl-media-picker');
    const frame = wp.media({
      title: 'Select or Upload Media / PDF',
      button: { text: 'Use this file' },
      multiple: false,
    });

    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      $picker.find('.pisl-media-val').val(attachment.id);
      const isImg = attachment.type === 'image' || /\.(png|jpe?g|webp|avif|gif|svg)$/i.test(attachment.url || '');
      if (isImg) {
        $picker.find('.pisl-media-preview').html('<img src="' + attachment.url + '" alt="" />');
      } else {
        $picker.find('.pisl-media-preview').html('<span class="pisl-file-pill">' + (attachment.filename || attachment.url) + '</span>');
      }
    });

    frame.open();
  });

  $(document).on('click', '.pisl-media-clear', function (e) {
    e.preventDefault();
    const $picker = $(this).closest('.pisl-media-picker');
    $picker.find('.pisl-media-val').val('');
    $picker.find('.pisl-media-preview').html('<span class="pisl-empty-pill">No file selected</span>');
  });

  // Gallery Serialization Helper
  function syncGalleryInput($gallery) {
    const vals = [];
    $gallery.find('.pisl-gallery-item').each(function () {
      const v = $(this).attr('data-val');
      if (v) vals.push(/^\d+$/.test(v) ? parseInt(v, 10) : v);
    });
    $gallery.find('.pisl-gallery-val').val(JSON.stringify(vals));
  }

  $(document).on('click', '.pisl-gallery-add', function (e) {
    e.preventDefault();
    const $gallery = $(this).closest('.pisl-gallery-picker');
    const frame = wp.media({
      title: 'Add Gallery Images',
      button: { text: 'Add to Gallery' },
      multiple: true,
    });

    frame.on('select', function () {
      const selection = frame.state().get('selection');
      selection.each(function (att) {
        const json = att.toJSON();
        const thumb = (json.sizes && json.sizes.thumbnail && json.sizes.thumbnail.url) || json.url;
        $gallery.find('.pisl-gallery-list').append(
          '<li class="pisl-gallery-item" data-val="' + json.id + '">' +
            '<img src="' + thumb + '" alt="" />' +
            '<button type="button" class="pisl-gallery-remove" title="Remove">&times;</button>' +
          '</li>'
        );
      });
      syncGalleryInput($gallery);
    });

    frame.open();
  });

  $(document).on('click', '.pisl-gallery-remove', function (e) {
    e.preventDefault();
    const $gallery = $(this).closest('.pisl-gallery-picker');
    $(this).closest('.pisl-gallery-item').remove();
    syncGalleryInput($gallery);
  });

  $('.pisl-gallery-list').sortable({
    update: function () {
      syncGalleryInput($(this).closest('.pisl-gallery-picker'));
    },
  });

  // Repeaters
  function initRepeaterSortable($ctx) {
    ($ctx ? $ctx.find('.pisl-repeater-rows') : $('.pisl-repeater-rows')).each(function () {
      const $rows = $(this);
      if ($rows.data('ui-sortable')) {
        $rows.sortable('refresh');
        return;
      }
      if (typeof $rows.sortable === 'function') {
        $rows.sortable({
          handle: '.pisl-repeater-handle',
          items: '> .pisl-repeater-row',
          axis: 'y',
          tolerance: 'pointer',
        });
      }
    });
  }

  initRepeaterSortable();

  $(document).on('click', '.pisl-repeater-add', function (e) {
    e.preventDefault();
    const $rep = $(this).closest('.pisl-repeater');
    const $rows = $rep.find('> .pisl-repeater-rows');
    const tpl = $rep.find('> .pisl-repeater-template').html();
    const nextIdx = Date.now();
    $rows.append(tpl.replace(/__INDEX__/g, String(nextIdx)));
    initRepeaterSortable($rep);
  });

  $(document).on('click', '.pisl-repeater-up', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-repeater-row');
    const $prev = $row.prev('.pisl-repeater-row');
    if ($prev.length) {
      $row.insertBefore($prev);
    }
  });

  $(document).on('click', '.pisl-repeater-down', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-repeater-row');
    const $next = $row.next('.pisl-repeater-row');
    if ($next.length) {
      $row.insertAfter($next);
    }
  });

  $(document).on('click', '.pisl-repeater-remove', function (e) {
    e.preventDefault();
    $(this).closest('.pisl-repeater-row').remove();
  });

  // Navigation Menu Builder (Visual, No-JSON)
  function initNavSortable($ctx) {
    const $target = $ctx ? $ctx.find('.pisl-nav-items') : $('.pisl-nav-items');
    if ($target.length && typeof $target.sortable === 'function') {
      $target.sortable({
        handle: '.pisl-nav-handle',
        items: '> .pisl-nav-item',
        axis: 'y',
        tolerance: 'pointer',
      });
    }
    const $subTarget = $ctx ? $ctx.find('.pisl-nav-sublinks-list') : $('.pisl-nav-sublinks-list');
    if ($subTarget.length && typeof $subTarget.sortable === 'function') {
      $subTarget.sortable({
        handle: '.pisl-sublink-handle',
        items: '> .pisl-nav-sublink-row',
        axis: 'y',
        tolerance: 'pointer',
      });
    }
  }

  initNavSortable();

  // Add Top-Level Menu Item
  $(document).on('click', '.pisl-add-nav-item-btn', function (e) {
    e.preventDefault();
    const $builder = $(this).closest('.pisl-nav-builder');
    const $list = $builder.find('> .pisl-nav-items');
    const tpl = $builder.find('> .pisl-nav-item-template').html();
    const nextIdx = Date.now();
    const rendered = tpl.replace(/__MAIN_IDX__/g, String(nextIdx));
    $list.append(rendered);
    initNavSortable($builder);
  });

  // Add Submenu Link
  $(document).on('click', '.pisl-add-sublink-btn', function (e) {
    e.preventDefault();
    const $item = $(this).closest('.pisl-nav-item');
    const $builder = $(this).closest('.pisl-nav-builder');
    const $subList = $item.find('.pisl-nav-sublinks-list');
    const mainIdx = $item.data('idx') || Date.now();
    const tpl = $builder.find('> .pisl-nav-sublink-template').html();
    const nextSubIdx = Date.now();
    const rendered = tpl
      .replace(/__MAIN_IDX__/g, String(mainIdx))
      .replace(/__SUB_IDX__/g, String(nextSubIdx));
    $subList.append(rendered);
    const count = $subList.find('.pisl-nav-sublink-row').length;
    $item.find('.pisl-sublink-count').text(count);
    initNavSortable($item);
  });

  // Reorder Main Items Up / Down
  $(document).on('click', '.pisl-nav-move-up', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-nav-item');
    const $prev = $row.prev('.pisl-nav-item');
    if ($prev.length) {
      $row.insertBefore($prev);
    }
  });

  $(document).on('click', '.pisl-nav-move-down', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-nav-item');
    const $next = $row.next('.pisl-nav-item');
    if ($next.length) {
      $row.insertAfter($next);
    }
  });

  // Remove Main Item
  $(document).on('click', '.pisl-nav-remove-item', function (e) {
    e.preventDefault();
    if (window.confirm('Remove this menu item and all its sublinks?')) {
      $(this).closest('.pisl-nav-item').remove();
    }
  });

  // Sublink Reorder Up / Down
  $(document).on('click', '.pisl-sublink-up', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-nav-sublink-row');
    const $prev = $row.prev('.pisl-nav-sublink-row');
    if ($prev.length) {
      $row.insertBefore($prev);
    }
  });

  $(document).on('click', '.pisl-sublink-down', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.pisl-nav-sublink-row');
    const $next = $row.next('.pisl-nav-sublink-row');
    if ($next.length) {
      $row.insertAfter($next);
    }
  });

  // Remove Sublink
  $(document).on('click', '.pisl-sublink-remove', function (e) {
    e.preventDefault();
    const $item = $(this).closest('.pisl-nav-item');
    $(this).closest('.pisl-nav-sublink-row').remove();
    const count = $item.find('.pisl-nav-sublink-row').length;
    $item.find('.pisl-sublink-count').text(count);
  });

  // Dynamic Header Title Update
  $(document).on('input', '.pisl-nav-label-input', function () {
    const val = $(this).val().trim();
    const $header = $(this).closest('.pisl-nav-item').find('.pisl-nav-title-text');
    $header.text(val || 'New Menu Link');
  });

  $(document).on('input', '.pisl-nav-href-input', function () {
    const val = $(this).val().trim();
    const $badge = $(this).closest('.pisl-nav-item').find('.pisl-nav-route-badge');
    if (val) {
      if ($badge.length) {
        $badge.text(val);
      } else {
        $(this).closest('.pisl-nav-item').find('.pisl-nav-item-header-left').append('<code class="pisl-nav-route-badge">' + val + '</code>');
      }
    } else {
      $badge.remove();
    }
  });

  // Live Google SERP Preview inside Single-Type SEO panel
  const $serpBox = $('#pisl-single-serp-preview');
  if ($serpBox.length) {
    const updateSingleSerp = function () {
      const fallbackTitle = $serpBox.data('default-title') || 'Pislinfra';
      const fallbackUrl = $serpBox.data('default-url') || 'https://pislinfra.com/';
      const t = ($('#pisl_seo_seoTitle').val() || $('#pisl_fields_metaTitle').val() || $('#pisl_fields_title').val() || fallbackTitle).trim();
      const d = ($('#pisl_seo_seoDescription').val() || $('#pisl_fields_metaDescription').val() || $('#pisl_fields_subtitle').val() || '').trim();
      const u = ($('#pisl_seo_canonicalUrl').val() || fallbackUrl).trim();
      $serpBox.find('.pisl-serp-title').text(t || fallbackTitle);
      $serpBox.find('.pisl-serp-url').text(u || fallbackUrl);
      $serpBox.find('.pisl-serp-desc').text(d ? d.substring(0, 160) : 'Add a meta description to preview search snippets.');
      const len = t.length;
      const dlen = d.length;
      $serpBox.find('.pisl-serp-counts').text('Title: ' + len + '/60 chars · Description: ' + dlen + '/160 chars');
    };
    $(document).on('input change', '#pisl_seo_seoTitle, #pisl_seo_seoDescription, #pisl_seo_canonicalUrl, #pisl_fields_metaTitle, #pisl_fields_metaDescription, #pisl_fields_title, #pisl_fields_subtitle', updateSingleSerp);
    updateSingleSerp();
  }

  // Ongoing Project -> Completed Project Workflow Button
  $(document).on('click', '#pisl-move-to-completed-btn', function (e) {
    e.preventDefault();
    if (!window.confirm('Promote this Ongoing Project to Completed Projects?')) {
      return;
    }
    const $btn = $(this);
    const postId = $btn.data('post-id');
    const $status = $('#pisl-move-status');
    $btn.prop('disabled', true).text('Promoting...');
    $status.text('');

    $.ajax({
      url: PISL_CMS_ADMIN.restBase + 'ongoing-projects/' + postId + '/move-to-completed',
      method: 'POST',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', PISL_CMS_ADMIN.nonce);
      },
      success: function (res) {
        $status.html('<span style="color:#15803d;font-weight:600;">Promoted cleanly! Redirecting to Completed Projects...</span>');
        if (res && res.data && res.data.id) {
          window.location.href = 'post.php?post=' + res.data.id + '&action=edit';
        } else {
          window.location.href = 'edit.php?post_type=completed_project';
        }
      },
      error: function (xhr) {
        const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to promote project.';
        $status.html('<span style="color:#b91c1c;">' + msg + '</span>');
        $btn.prop('disabled', false).text('Move to Completed Project');
      },
    });
  });
})(jQuery);
