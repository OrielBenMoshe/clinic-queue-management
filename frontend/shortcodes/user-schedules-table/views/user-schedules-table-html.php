<?php
/**
 * תצוגת טבלת היומנים של המשתמש — [user_schedules_table].
 * HTML בלבד; ללא CSS/JS inline.
 *
 * @package Clinic_Queue_Management
 * @var array $data מכיל: state, rows.
 */

if (!defined('ABSPATH')) {
    exit;
}

$state_raw = isset($data['state']) ? (string) $data['state'] : 'ready';
$rows      = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();

if ('login_required' === $state_raw) {
    ?>
    <div class="user-schedules-table-root" dir="rtl">
        <p class="schedule-table__notice" role="status">
            <?php echo esc_html__('יש להתחבר למערכת כדי להציג את היומנים שלך.', 'clinic-queue-management'); ?>
        </p>
    </div>
    <?php
    return;
}

$icon_url_dots = plugins_url('assets/images/icons/dots-vertical-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);

?>
<div class="user-schedules-table-root">
    <div class="schedule-table">
        <div class="schedule-table__scroll-wrapper">
        <table class="schedule-table__table" role="grid">
            <thead class="schedule-table__head">
                <tr>
                    <th class="schedule-table__th schedule-table__th--sortable" scope="col"
                        data-sort="name" tabindex="0" role="button" aria-sort="none">
                        <span class="schedule-table__th-text"><?php echo esc_html__('שם רופא/יומן', 'clinic-queue-management'); ?></span>
                        <span class="schedule-table__th-arrow" aria-hidden="true">
                            <svg class="schedule-table__chevron" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 10l5 5 5-5"/>
                            </svg>
                        </span>
                    </th>
                    <th class="schedule-table__th schedule-table__th--sortable" scope="col"
                        data-sort="clinic" tabindex="0" role="button" aria-sort="none">
                        <span class="schedule-table__th-text"><?php echo esc_html__('שם מרפאה', 'clinic-queue-management'); ?></span>
                        <span class="schedule-table__th-arrow" aria-hidden="true">
                            <svg class="schedule-table__chevron" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 10l5 5 5-5"/>
                            </svg>
                        </span>
                    </th>
                    <th class="schedule-table__th schedule-table__th--sortable" scope="col"
                        data-sort="status" tabindex="0" role="button" aria-sort="none">
                        <span class="schedule-table__th-text"><?php echo esc_html__('סטטוס יומן', 'clinic-queue-management'); ?></span>
                        <span class="schedule-table__th-arrow" aria-hidden="true">
                            <svg class="schedule-table__chevron" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 10l5 5 5-5"/>
                            </svg>
                        </span>
                    </th>
                    <th class="schedule-table__th" scope="col">
                        <span class="schedule-table__th-text"><?php echo esc_html__('התמחות עיקרית', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="schedule-table__th" scope="col">
                        <span class="schedule-table__th-text"><?php echo esc_html__('ימי פעילות', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="schedule-table__th" scope="col">
                        <span class="schedule-table__th-text"><?php echo esc_html__('תורים קרובים', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="schedule-table__th schedule-table__actions-col" scope="col"></th>
                </tr>
            </thead>
            <tbody class="schedule-table__body">
                <?php if (empty($rows)) : ?>
                    <tr class="schedule-table__row schedule-table__row--empty">
                        <td colspan="7" class="schedule-table__empty">
                            <?php echo esc_html__('אין יומנים להצגה', 'clinic-queue-management'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $item) :
                        $schedule_id  = isset($item['schedule_id']) ? absint($item['schedule_id']) : 0;
                        $display_name = isset($item['display_name']) ? (string) $item['display_name'] : '--';
                        $doctor_image = isset($item['doctor_image']) ? (string) $item['doctor_image'] : '';
                        $doctor_url   = isset($item['doctor_url']) ? (string) $item['doctor_url'] : '';
                        $clinics_text = isset($item['clinics_text']) ? (string) $item['clinics_text'] : '--';
                        $is_active    = !empty($item['is_active']);
                        $status_label = isset($item['status_label']) ? (string) $item['status_label'] : '';
                        $days_text    = isset($item['days_text']) ? (string) $item['days_text'] : '--';
                        $specialties  = isset($item['specialties']) && is_array($item['specialties']) ? $item['specialties'] : array();
                    ?>
                    <?php
                        $schedule_type = isset($item['schedule_type']) ? (string) $item['schedule_type'] : 'google';
                    ?>
                    <tr class="schedule-table__row"
                        data-id="<?php echo esc_attr((string) $schedule_id); ?>"
                        data-schedule-type="<?php echo esc_attr($schedule_type); ?>">

                        <td class="schedule-table__name"
                            data-sort-name="<?php echo esc_attr($display_name); ?>">
                            <div class="schedule-table__doctor">
                                <?php if ('' !== $doctor_image) : ?>
                                    <img class="schedule-table__doctor-image"
                                        src="<?php echo esc_url($doctor_image); ?>" alt="">
                                <?php else : ?>
                                    <span class="schedule-table__doctor-placeholder" aria-hidden="true">
                                        <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" role="img">
                                            <mask id="mask0_schedule_placeholder_<?php echo esc_attr((string) $schedule_id); ?>"
                                                style="mask-type:luminance" maskUnits="userSpaceOnUse"
                                                x="0" y="0" width="30" height="30">
                                                <path d="M0 0H30V30H0V0Z" fill="white"/>
                                            </mask>
                                            <g mask="url(#mask0_schedule_placeholder_<?php echo esc_attr((string) $schedule_id); ?>)">
                                                <path d="M3.28125 3.51557H26.7188C27.3633 3.51557 27.8906 4.04285 27.8906 4.68744V28.2422C27.8906 28.8868 27.3633 29.4141 26.7188 29.4141H3.28125C2.63672 29.4141 2.10938 28.8868 2.10938 28.2422V4.68744C2.10938 4.04285 2.63672 3.51557 3.28125 3.51557Z" stroke="#0E4E6D" stroke-width="1.5" stroke-miterlimit="22.926" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M7.96875 5.85938C7.16309 5.85938 6.50391 5.2002 6.50391 4.39453V2.05078C6.50391 1.24512 7.16309 0.585937 7.96875 0.585937C8.77441 0.585937 9.43359 1.24512 9.43359 2.05078V3.22266" stroke="#0E4E6D" stroke-width="1.5" stroke-miterlimit="22.926" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M15 5.85938C14.1943 5.85938 13.5352 5.2002 13.5352 4.39453V2.05078C13.5352 1.24512 14.1943 0.585937 15 0.585937C15.8057 0.585937 16.4648 1.24512 16.4648 2.05078V3.22266" stroke="#0E4E6D" stroke-width="1.5" stroke-miterlimit="22.926" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M22.0312 5.85938C21.2256 5.85938 20.5664 5.2002 20.5664 4.39453V2.05078C20.5664 1.24512 21.2256 0.585937 22.0312 0.585937C22.8369 0.585937 23.4961 1.24512 23.4961 2.05078V3.22266" stroke="#0E4E6D" stroke-width="1.5" stroke-miterlimit="22.926" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M2.40234 8.20312H27.5977" stroke="#0E4E6D" stroke-width="1.5" stroke-miterlimit="22.926" stroke-linecap="round" stroke-linejoin="round"/>
                                            </g>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                                <?php if ('' !== $doctor_url) : ?>
                                    <a class="schedule-table__doctor-link"
                                        href="<?php echo esc_url($doctor_url); ?>">
                                        <span class="schedule-table__doctor-name"><?php echo esc_html($display_name); ?></span>
                                    </a>
                                <?php else : ?>
                                    <span class="schedule-table__doctor-name"><?php echo esc_html($display_name); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="schedule-table__clinic"
                            data-sort-clinic="<?php echo esc_attr($clinics_text); ?>">
                            <span class="schedule-table__clinic-name"><?php echo esc_html($clinics_text); ?></span>
                        </td>

                        <td class="schedule-table__status"
                            data-sort-status="<?php echo esc_attr($status_label); ?>">
                            <span class="schedule-table__status-badge schedule-table__status-badge--<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>

                        <td class="schedule-table__specialties">
                            <span class="schedule-table__tags">
                                <?php if (!empty($specialties)) :
                                    $first_name = array_shift($specialties);
                                    $more_count = count($specialties);
                                ?>
                                    <span class="schedule-table__tag"><?php echo esc_html($first_name); ?></span>
                                    <?php if ($more_count > 0) : ?>
                                        <span class="schedule-table__tag schedule-table__tag--more">+<?php echo esc_html((string) $more_count); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </td>

                        <td class="schedule-table__days">
                            <?php echo esc_html($days_text); ?>
                        </td>

                        <td class="schedule-table__appointments">
                            <?php echo esc_html('--'); ?>
                        </td>

                        <td class="schedule-table__actions">
                            <div class="schedule-table__menu-wrapper">
                                <button type="button" class="schedule-table__menu-button"
                                    aria-haspopup="true" aria-expanded="false"
                                    aria-label="<?php echo esc_attr__('פעולות', 'clinic-queue-management'); ?>">
                                    <img src="<?php echo esc_url($icon_url_dots); ?>" alt=""
                                        aria-hidden="true" width="16" height="16">
                                </button>
                                <div class="schedule-table__menu" role="menu" hidden>
                                    <button type="button" class="schedule-table__edit-button" role="menuitem">
                                        <?php echo esc_html__('עריכת יומן', 'clinic-queue-management'); ?>
                                    </button>
                                    <button type="button" class="schedule-table__delete-button" role="menuitem">
                                        <?php echo esc_html__('מחיקת יומן', 'clinic-queue-management'); ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div><!-- /.schedule-table__scroll-wrapper -->
    </div>

    <!-- מודל עריכת יומן -->
    <div class="schedule-table__edit-modal-overlay"
         id="schedule-table-edit-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="schedule-table-edit-modal-title"
         hidden>
        <div class="schedule-table__edit-modal">

            <div class="schedule-table__edit-modal-header">
                <h2 class="schedule-table__edit-modal-title" id="schedule-table-edit-modal-title">
                    <?php echo esc_html__('עריכת יומן', 'clinic-queue-management'); ?>
                </h2>
                <button type="button" class="schedule-table__edit-modal-close"
                    aria-label="<?php echo esc_attr__('סגירה', 'clinic-queue-management'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="schedule-table__edit-modal-loader" id="schedule-table-edit-modal-loader" hidden>
                <span class="schedule-table__edit-modal-spinner" aria-hidden="true"></span>
                <span><?php echo esc_html__('טוען...', 'clinic-queue-management'); ?></span>
            </div>

            <div class="schedule-table__edit-modal-body" id="schedule-table-edit-modal-body" hidden>

            <div class="jet-form-builder jet-form-builder--default clinic-add-schedule-form clinic-queue-jetform-mui"
                 data-schedule-form-role="edit-modal">

                <?php
                Clinic_Schedule_Form_Manager::render_partial(
                    'schedule-settings-panel',
                    Clinic_Schedule_Form_Manager::get_schedule_settings_config('edit_modal')
                );
                ?>

            </div><!-- /.clinic-add-schedule-form -->

                <!-- שגיאה -->
                <div class="schedule-table__edit-modal-error"
                     id="schedule-table-edit-modal-error"
                     role="alert"
                     hidden></div>

                <!-- הצלחה -->
                <div class="schedule-table__edit-modal-success"
                     id="schedule-table-edit-modal-success"
                     role="status"
                     hidden></div>

            </div><!-- /#schedule-table-edit-modal-body -->

            <div class="schedule-table__edit-modal-footer" id="schedule-table-edit-modal-footer" hidden>
                <button type="button" class="schedule-table__edit-modal-save" id="schedule-table-edit-modal-save">
                    <?php echo esc_html__('שמירה', 'clinic-queue-management'); ?>
                </button>
                <button type="button" class="schedule-table__edit-modal-cancel-btn" id="schedule-table-edit-modal-cancel">
                    <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
                </button>
            </div>

        </div><!-- /.schedule-table__edit-modal -->
    </div><!-- /#schedule-table-edit-modal -->

    <div class="schedule-table__delete-modal-overlay"
         id="schedule-table-delete-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="schedule-table-delete-modal-title"
         hidden>
        <div class="schedule-table__delete-modal">
            <div class="schedule-table__delete-modal-content">
                <h2 class="schedule-table__delete-modal-title" id="schedule-table-delete-modal-title">
                    <?php echo esc_html__('מחיקת יומן', 'clinic-queue-management'); ?>
                </h2>
                <p class="schedule-table__delete-modal-body" id="schedule-table-delete-modal-body">
                    <?php echo esc_html__('האם אתה בטוח שברצונך למחוק את היומן? פעולה זו אינה ניתנת לביטול.', 'clinic-queue-management'); ?>
                </p>
                <div class="schedule-table__delete-modal-error"
                     id="schedule-table-delete-modal-error"
                     role="alert"
                     hidden></div>
            </div>
            <div class="schedule-table__delete-modal-actions">
                <button type="button" class="schedule-table__delete-modal-confirm" id="schedule-table-delete-modal-confirm">
                    <?php echo esc_html__('מחיקה', 'clinic-queue-management'); ?>
                </button>
                <button type="button" class="schedule-table__delete-modal-cancel" id="schedule-table-delete-modal-cancel">
                    <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
