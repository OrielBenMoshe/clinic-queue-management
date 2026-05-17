<?php
/**
 * תצוגת טבלת מרפאות ולוח זמנים — [user_doctor_clinics_table].
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

/* ── הודעות מצב (לא מחובר / אין פרופיל) ── */
if ('login_required' === $state_raw) {
    ?>
    <div class="user-clinics-table-root" dir="rtl">
        <p class="clinics-table__notice" role="status">
            <?php echo esc_html__('יש להתחבר למערכת כדי להציג את המרפאות שלך.', 'clinic-queue-management'); ?>
        </p>
    </div>
    <?php
    return;
}

if ('no_doctor_profile' === $state_raw) {
    ?>
    <div class="user-clinics-table-root" dir="rtl">
        <p class="clinics-table__notice" role="status">
            <?php echo esc_html__('לא נמצא פרופיל רופא המקושר לחשבון המשתמש שלך.', 'clinic-queue-management'); ?>
        </p>
    </div>
    <?php
    return;
}

$icon_url_dots = plugins_url('assets/images/icons/dots-vertical-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$doctor_id     = isset($data['doctor_id']) ? absint($data['doctor_id']) : 0;

/* ── מיפוי badge_modifier מהשירות לסיומת CSS המקורית ── */
$badge_mod_map = array(
    'is-neutral' => 'none',
    'is-active'  => 'active',
    'is-pending' => 'pending-connect',
    'is-frozen'  => 'frozen',
);
?>
<div class="user-clinics-table-root" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>">
    <div class="clinics-table">
        <table class="clinics-table__table" role="grid">
            <thead class="clinics-table__head">
                <tr>
                    <th class="clinics-table__th clinics-table__th--sortable" scope="col"
                        data-sort="name" tabindex="0" role="button" aria-sort="none">
                        <span class="clinics-table__th-text"><?php echo esc_html__('שם מרפאה', 'clinic-queue-management'); ?></span>
                        <span class="clinics-table__th-arrow" aria-hidden="true">
                            <svg class="clinics-table__chevron" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 10l5 5 5-5"/>
                            </svg>
                        </span>
                    </th>
                    <th class="clinics-table__th" scope="col">
                        <span class="clinics-table__th-text"><?php echo esc_html__('כתובת', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="clinics-table__th clinics-table__th--sortable" scope="col"
                        data-sort="status" tabindex="0" role="button" aria-sort="none">
                        <span class="clinics-table__th-text"><?php echo esc_html__('סטטוס יומן', 'clinic-queue-management'); ?></span>
                        <span class="clinics-table__th-arrow" aria-hidden="true">
                            <svg class="clinics-table__chevron" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 10l5 5 5-5"/>
                            </svg>
                        </span>
                    </th>
                    <th class="clinics-table__th" scope="col">
                        <span class="clinics-table__th-text"><?php echo esc_html__('ימי פעילות', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="clinics-table__th" scope="col">
                        <span class="clinics-table__th-text"><?php echo esc_html__('תורים פתוחים', 'clinic-queue-management'); ?></span>
                    </th>
                    <th class="clinics-table__th" scope="col"></th>
                </tr>
            </thead>
            <tbody class="clinics-table__body">
                <?php if (empty($rows)) : ?>
                    <tr class="clinics-table__row clinics-table__row--empty">
                        <td colspan="6" class="clinics-table__empty">
                            <?php echo esc_html__('אין מרפאות משויכות', 'clinic-queue-management'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $item) :
                        $badge_svc      = isset($item['badge_modifier']) ? (string) $item['badge_modifier'] : 'is-neutral';
                        $badge_mod      = isset($badge_mod_map[$badge_svc]) ? $badge_mod_map[$badge_svc] : 'none';
                        $badge_label    = isset($item['badge_label']) ? (string) $item['badge_label'] : '';
                        $clinic_title   = isset($item['clinic_title']) ? (string) $item['clinic_title'] : '';
                        $clinic_address = isset($item['clinic_address']) ? (string) $item['clinic_address'] : '';
                        $working_days   = isset($item['working_days_text']) ? (string) $item['working_days_text'] : '';
                        $connect_url    = isset($item['doctor_connect_url']) ? (string) $item['doctor_connect_url'] : '';
                        $clinic_id      = isset($item['clinic_id']) ? absint($item['clinic_id']) : 0;
                        $is_highlight   = 'pending-connect' === $badge_mod;
                        $open_count     = isset($item['open_appointments_count']) ? $item['open_appointments_count'] : null;
                    ?>
                    <tr class="clinics-table__row<?php echo $is_highlight ? ' clinics-table__row--highlight' : ''; ?>"
                        data-id="<?php echo esc_attr((string) $clinic_id); ?>">

                        <td class="clinics-table__name"
                            data-sort-name="<?php echo esc_attr($clinic_title); ?>">
                            <?php echo esc_html($clinic_title ?: '—'); ?>
                        </td>

                        <td class="clinics-table__address">
                            <?php echo esc_html($clinic_address ?: '—'); ?>
                        </td>

                        <td class="clinics-table__status"
                            data-sort-status="<?php echo esc_attr($badge_label); ?>">
                            <span class="clinics-table__status-badge clinics-table__status-badge--<?php echo esc_attr($badge_mod); ?>">
                                <?php echo esc_html($badge_label); ?>
                            </span>
                        </td>

                        <td class="clinics-table__days">
                            <?php echo esc_html($working_days ?: '—'); ?>
                        </td>

                        <td class="clinics-table__appointments">
                            <?php echo esc_html('active' === $badge_mod && null !== $open_count ? (string) $open_count : '—'); ?>
                        </td>

                        <td class="clinics-table__connect">
                            <?php if ('' !== $connect_url && 'frozen' !== $badge_mod) : ?>
                                <a class="clinics-table__connect-btn"
                                    href="<?php echo esc_url($connect_url); ?>"
                                    target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html__('קישור יומן לגוגל', 'clinic-queue-management'); ?>
                                </a>
                            <?php endif; ?>
                            <div class="clinics-table__actions"
                                data-clinic-id="<?php echo esc_attr((string) $clinic_id); ?>"
                                data-schedule-id="<?php echo esc_attr((string) (isset($item['schedule_id']) ? absint($item['schedule_id']) : 0)); ?>"
                                data-badge-mod="<?php echo esc_attr($badge_mod); ?>">
                                <button
                                    type="button"
                                    class="clinics-table__actions-trigger"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    aria-label="<?php echo esc_attr__('פעולות', 'clinic-queue-management'); ?>"
                                >
                                    <img
                                        src="<?php echo esc_url($icon_url_dots); ?>"
                                        alt=""
                                        aria-hidden="true"
                                        width="16"
                                        height="16"
                                    >
                                </button>
                                <div class="clinics-table__actions-menu" role="menu" hidden>
                                    <?php if ('active' === $badge_mod) : ?>
                                        <button type="button" class="clinics-table__actions-item" data-action="freeze" role="menuitem">
                                            <?php echo esc_html__('הקפאת יומן', 'clinic-queue-management'); ?>
                                        </button>
                                    <?php elseif ('frozen' === $badge_mod) : ?>
                                        <button type="button" class="clinics-table__actions-item" data-action="unfreeze" role="menuitem">
                                            <?php echo esc_html__('הפעלת יומן', 'clinic-queue-management'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="clinics-table__actions-item clinics-table__actions-item--danger" data-action="detach" role="menuitem">
                                        <?php echo esc_html__('התנתקות מהמרפאה', 'clinic-queue-management'); ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="clinics-table__detach-modal-overlay"
         id="clinics-table-detach-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="clinics-table-detach-modal-title"
         hidden>
        <div class="clinics-table__detach-modal">
            <h2 class="clinics-table__detach-modal-title" id="clinics-table-detach-modal-title">
                <?php echo esc_html__('התנתקות מהמרפאה', 'clinic-queue-management'); ?>
            </h2>
            <p class="clinics-table__detach-modal-body">
                <?php echo esc_html__('האם אתה בטוח שברצונך להתנתק ממרפאה זו? לאחר האישור לא תהיה משויך יותר למרפאה.', 'clinic-queue-management'); ?>
            </p>
            <div class="clinics-table__detach-modal-error"
                 id="clinics-table-detach-modal-error"
                 role="alert"
                 hidden></div>
            <div class="clinics-table__detach-modal-actions">
                <button type="button" class="clinics-table__detach-modal-confirm" id="clinics-table-detach-modal-confirm">
                    <?php echo esc_html__('אישור', 'clinic-queue-management'); ?>
                </button>
                <button type="button" class="clinics-table__detach-modal-cancel" id="clinics-table-detach-modal-cancel">
                    <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="clinics-table__detach-modal-overlay"
         id="clinics-table-activate-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="clinics-table-activate-modal-title"
         hidden>
        <div class="clinics-table__detach-modal">
            <h2 class="clinics-table__detach-modal-title" id="clinics-table-activate-modal-title">
                <?php echo esc_html__('הפעלת יומן', 'clinic-queue-management'); ?>
            </h2>
            <p class="clinics-table__detach-modal-body">
                <?php echo esc_html__('האם להפעיל מחדש את היומן? מרגע ההפעלה ניתן יהיה לקבוע תורים ביומן.', 'clinic-queue-management'); ?>
            </p>
            <div class="clinics-table__detach-modal-error"
                 id="clinics-table-activate-modal-error"
                 role="alert"
                 hidden></div>
            <div class="clinics-table__detach-modal-actions">
                <button type="button" class="clinics-table__detach-modal-confirm" id="clinics-table-activate-modal-confirm">
                    <?php echo esc_html__('אישור', 'clinic-queue-management'); ?>
                </button>
                <button type="button" class="clinics-table__detach-modal-cancel" id="clinics-table-activate-modal-cancel">
                    <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="clinics-table__detach-modal-overlay"
         id="clinics-table-freeze-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="clinics-table-freeze-modal-title"
         hidden>
        <div class="clinics-table__detach-modal">
            <h2 class="clinics-table__detach-modal-title" id="clinics-table-freeze-modal-title">
                <?php echo esc_html__('הקפאת יומן', 'clinic-queue-management'); ?>
            </h2>
            <p class="clinics-table__detach-modal-body">
                <?php echo esc_html__('האם להקפיא את היומן? יותר לא יוכלו לקבוע תורים דרך המערכת.', 'clinic-queue-management'); ?>
            </p>
            <div class="clinics-table__detach-modal-error"
                 id="clinics-table-freeze-modal-error"
                 role="alert"
                 hidden></div>
            <div class="clinics-table__detach-modal-actions">
                <button type="button" class="clinics-table__detach-modal-confirm" id="clinics-table-freeze-modal-confirm">
                    <?php echo esc_html__('הקפאה', 'clinic-queue-management'); ?>
                </button>
                <button type="button" class="clinics-table__detach-modal-cancel" id="clinics-table-freeze-modal-cancel">
                    <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
