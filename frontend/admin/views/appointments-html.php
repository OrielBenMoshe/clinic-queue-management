<?php
/**
 * Appointments Management Page View
 * תצוגת עמוד ניהול התורים
 * 
 * @package ClinicQueue
 * @subpackage Admin\Views
 * @since 2.0.0
 * 
 * Variables available:
 * @var array  $appointments List of appointments
 * @var array  $stats        Statistics data
 * @var string $filter       'all'|'past'|'future'
 * @var string $orderby      Current sort column
 * @var string $order        'ASC'|'DESC'
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$filter = isset($filter) ? $filter : 'all';
$first_visit = isset($first_visit) ? $first_visit : 'all';
$orderby = isset($orderby) ? $orderby : 'appointment_datetime';
$order = isset($order) ? $order : 'DESC';

$base_url = remove_query_arg(array('orderby', 'order', 'filter', 'first_visit'));
$sort_link = function($col) use ($base_url, $orderby, $order, $filter, $first_visit) {
    $new_order = ($orderby === $col && $order === 'DESC') ? 'ASC' : 'DESC';
    $args = array('orderby' => $col, 'order' => $new_order);
    if ($filter !== 'all') {
        $args['filter'] = $filter;
    }
    if ($first_visit !== 'all') {
        $args['first_visit'] = $first_visit;
    }
    return add_query_arg($args, $base_url);
};

$get_appointment_status = function($appointment_datetime) {
    if (empty($appointment_datetime)) {
        return 'future';
    }
    $ts = strtotime($appointment_datetime);
    if ($ts === false) {
        return 'future';
    }
    return $ts < time() ? 'past' : 'future';
};

$sort_header = function($col, $label) use ($sort_link, $orderby, $order) {
    $is_asc_active = ($orderby === $col && $order === 'ASC');
    $is_desc_active = ($orderby === $col && $order === 'DESC');
    ?>
    <a href="<?php echo esc_url($sort_link($col)); ?>">
        <span class="sortable-label-text"><?php echo esc_html($label); ?></span>
        <span class="sortable-arrows">
            <span class="sort-arrow sort-arrow--up dashicons dashicons-arrow-up-alt2 <?php echo $is_asc_active ? ' is-active' : ''; ?>" aria-hidden="true"></span>
            <span class="sort-arrow sort-arrow--down dashicons dashicons-arrow-down-alt2 <?php echo $is_desc_active ? ' is-active' : ''; ?>" aria-hidden="true"></span>
        </span>
    </a>
    <?php
};
?>

<div class="wrap clinic-queue-appointments-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('ניהול תורים', 'clinic-queue'); ?>
    </h1>
    
    <button type="button" class="page-title-action clinic-queue-create-test-btn">
        <?php esc_html_e('יצירת רשומת בדיקה', 'clinic-queue'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <!-- Statistics -->
    <div class="clinic-queue-stats">
        <div class="clinic-queue-stat-card">
            <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
            <div class="stat-label"><?php esc_html_e('סך הכל תורים', 'clinic-queue'); ?></div>
        </div>
        <div class="clinic-queue-stat-card clinic-queue-stat-past">
            <div class="stat-value"><?php echo esc_html($stats['past'] ?? 0); ?></div>
            <div class="stat-label"><?php esc_html_e('תורים שעברו', 'clinic-queue'); ?></div>
        </div>
        <div class="clinic-queue-stat-card clinic-queue-stat-future">
            <div class="stat-value"><?php echo esc_html($stats['future'] ?? 0); ?></div>
            <div class="stat-label"><?php esc_html_e('תורים שיהיו', 'clinic-queue'); ?></div>
        </div>
    </div>
    
    <!-- Filter + Column visibility -->
    <div class="clinic-queue-toolbar">
        <div class="clinic-queue-appointments-filters">
            <a href="<?php echo esc_url(add_query_arg(array('filter' => 'all', 'first_visit' => $first_visit), $base_url)); ?>" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('הכל', 'clinic-queue'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('filter' => 'past', 'first_visit' => $first_visit), $base_url)); ?>" class="button <?php echo $filter === 'past' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('תורים שעברו', 'clinic-queue'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('filter' => 'future', 'first_visit' => $first_visit), $base_url)); ?>" class="button <?php echo $filter === 'future' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('תורים שיהיו', 'clinic-queue'); ?>
            </a>
            <span class="clinic-queue-filter-sep" aria-hidden="true"></span>
            <span class="clinic-queue-filter-label"><?php esc_html_e('ביקור ראשון במרפאה:', 'clinic-queue'); ?></span>
            <a href="<?php echo esc_url(add_query_arg(array('filter' => $filter, 'first_visit' => 'yes'), $base_url)); ?>" class="button <?php echo $first_visit === 'yes' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('כן', 'clinic-queue'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('filter' => $filter, 'first_visit' => 'no'), $base_url)); ?>" class="button <?php echo $first_visit === 'no' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('לא', 'clinic-queue'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('filter' => $filter, 'first_visit' => 'all'), $base_url)); ?>" class="button <?php echo $first_visit === 'all' ? 'button-primary' : ''; ?>">
                <?php esc_html_e('הכל', 'clinic-queue'); ?>
            </a>
        </div>
        <div class="clinic-queue-columns-toggle">
            <button type="button" class="button clinic-queue-columns-btn" aria-expanded="false" aria-haspopup="true">
                <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-left: 4px;"></span>
                <?php esc_html_e('עמודות להצגה', 'clinic-queue'); ?>
            </button>
            <div class="clinic-queue-columns-dropdown" id="clinic-queue-columns-dropdown" role="menu" aria-hidden="true">
                <div class="clinic-queue-columns-dropdown__title"><?php esc_html_e('הצג/הסתר עמודות', 'clinic-queue'); ?></div>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="id" checked> <?php esc_html_e('מזהה', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="patient" checked> <?php esc_html_e('שם מטופל', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="phone" checked> <?php esc_html_e('טלפון', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="id_number" checked> <?php esc_html_e('ת.ז.', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="datetime" checked> <?php esc_html_e('תאריך ושעה', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="duration" checked> <?php esc_html_e('משך', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="status" checked> <?php esc_html_e('סטטוס', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="clinic" checked> <?php esc_html_e('מרפאה', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="doctor" checked> <?php esc_html_e('רופא', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="user" checked> <?php esc_html_e('משתמש', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="first_visit" checked> <?php esc_html_e('ביקור ראשון', 'clinic-queue'); ?></label>
                <label class="clinic-queue-columns-dropdown__item"><input type="checkbox" data-column="actions" checked> <?php esc_html_e('פעולות', 'clinic-queue'); ?></label>
            </div>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="clinic-queue-table-container">
        <table class="wp-list-table widefat fixed striped clinic-queue-appointments-table">
            <thead>
                <tr>
                    <th class="column-id sortable"><?php $sort_header('id', __('מזהה', 'clinic-queue')); ?></th>
                    <th class="column-patient sortable"><?php $sort_header('patient_name', __('שם מטופל', 'clinic-queue')); ?></th>
                    <th class="column-phone sortable"><?php $sort_header('patient_phone', __('טלפון', 'clinic-queue')); ?></th>
                    <th class="column-id-number sortable"><?php $sort_header('patient_id_number', __('ת.ז.', 'clinic-queue')); ?></th>
                    <th class="column-datetime sortable"><?php $sort_header('appointment_datetime', __('תאריך', 'clinic-queue')); ?></th>
                    <th class="column-duration sortable"><?php $sort_header('duration', __('משך (דק\')', 'clinic-queue')); ?></th>
                    <th class="column-status"><?php esc_html_e('סטטוס', 'clinic-queue'); ?></th>
                    <th class="column-first-visit sortable"><?php $sort_header('first_visit', __('ביקור ראשון', 'clinic-queue')); ?></th>
                    <th class="column-clinic sortable"><?php $sort_header('wp_clinic_id', __('מרפאה', 'clinic-queue')); ?></th>
                    <th class="column-doctor sortable"><?php $sort_header('wp_doctor_id', __('רופא', 'clinic-queue')); ?></th>
                    <th class="column-user sortable"><?php $sort_header('created_by', __('נוצר על ידי', 'clinic-queue')); ?></th>
                    <th class="column-actions"><?php esc_html_e('פעולות', 'clinic-queue'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)) : ?>
                    <tr class="no-items">
                        <td colspan="12" class="colspanchange">
                            <?php esc_html_e('לא נמצאו תורים. לחץ על "יצירת רשומת בדיקה" כדי להתחיל.', 'clinic-queue'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($appointments as $appointment) : ?>
                        <tr data-appointment-id="<?php echo esc_attr($appointment['id']); ?>">
                            <td class="column-id">
                                <?php echo esc_html($appointment['id']); ?>
                            </td>
                            <td class="column-patient">
                                <strong>
                                    <?php echo esc_html($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                </strong>
                                <?php if (!empty($appointment['patient_email'])) : ?>
                                    <br>
                                    <small><?php echo esc_html($appointment['patient_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="column-phone">
                                <?php echo esc_html($appointment['patient_phone']); ?>
                            </td>
                            <td class="column-id-number">
                                <?php echo !empty($appointment['patient_id_number']) ? esc_html($appointment['patient_id_number']) : '—'; ?>
                            </td>
                            <td class="column-datetime">
                                <?php 
                                // Format datetime
                                $datetime = $appointment['appointment_datetime'];
                                if (strtotime($datetime)) {
                                    echo esc_html(date_i18n('d/m/Y H:i', strtotime($datetime)));
                                } else {
                                    echo esc_html($datetime);
                                }
                                ?>
                            </td>
                            <td class="column-duration">
                                <?php echo esc_html($appointment['duration']); ?>
                            </td>
                            <td class="column-status">
                                <?php
                                $row_status = $get_appointment_status($appointment['appointment_datetime']);
                                $status_label = $row_status === 'past' ? __('עבר', 'clinic-queue') : __('יהיה', 'clinic-queue');
                                ?>
                                <span class="clinic-queue-status-badge clinic-queue-status-badge--<?php echo esc_attr($row_status); ?>"><?php echo esc_html($status_label); ?></span>
                            </td>
                            <td class="column-first-visit">
                                <?php echo !empty($appointment['first_visit']) ? esc_html__('כן', 'clinic-queue') : esc_html__('לא', 'clinic-queue'); ?>
                            </td>
                            <td class="column-clinic">
                                <?php
                                $clinic_id = isset($appointment['wp_clinic_id']) ? (int) $appointment['wp_clinic_id'] : 0;
                                if ($clinic_id > 0) {
                                    $clinic_post = get_post($clinic_id);
                                    $clinic_name = $clinic_post ? $clinic_post->post_title : (string) $clinic_id;
                                    $clinic_url = get_permalink($clinic_id);
                                    if ($clinic_url) {
                                        echo '<a href="' . esc_url($clinic_url) . '" class="clinic-queue-cell-link" title="' . esc_attr($clinic_name) . '">';
                                        echo esc_html($clinic_name);
                                        echo '</a>';
                                    } else {
                                        echo '<span class="clinic-queue-cell-text" title="' . esc_attr($clinic_name) . '">' . esc_html($clinic_name) . '</span>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="column-doctor">
                                <?php
                                $doctor_id = isset($appointment['wp_doctor_id']) ? (int) $appointment['wp_doctor_id'] : 0;
                                if ($doctor_id > 0) {
                                    $doctor_post = get_post($doctor_id);
                                    $doctor_name = $doctor_post ? $doctor_post->post_title : (string) $doctor_id;
                                    $doctor_url = get_permalink($doctor_id);
                                    if ($doctor_url) {
                                        echo '<a href="' . esc_url($doctor_url) . '" class="clinic-queue-cell-link" title="' . esc_attr($doctor_name) . '">';
                                        echo esc_html($doctor_name);
                                        echo '</a>';
                                    } else {
                                        echo '<span class="clinic-queue-cell-text" title="' . esc_attr($doctor_name) . '">' . esc_html($doctor_name) . '</span>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="column-user">
                                <?php
                                $created_by = isset($appointment['created_by']) ? (int) $appointment['created_by'] : 0;
                                if ($created_by > 0) {
                                    $user = get_userdata($created_by);
                                    $user_name = $user ? $user->display_name : (string) $created_by;
                                    echo '<span class="clinic-queue-cell-text" title="' . esc_attr($user_name) . '">' . esc_html($user_name) . '</span>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="column-actions">
                                <button 
                                    type="button" 
                                    class="button button-small clinic-queue-delete-btn"
                                    data-appointment-id="<?php echo esc_attr($appointment['id']); ?>"
                                    title="<?php esc_attr_e('מחק תור', 'clinic-queue'); ?>"
                                >
                                    <?php esc_html_e('מחק', 'clinic-queue'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Loading Overlay -->
    <div class="clinic-queue-loading-overlay" style="display: none;">
        <div class="clinic-queue-spinner"></div>
    </div>
</div>
