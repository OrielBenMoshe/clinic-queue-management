<?php
/**
 * רדיו-באטונים לבחירת מטופל (יוזר ראשי / בני משפחה).
 *
 * @package Clinic_Queue_Management
 *
 * @var WP_User              $current_user
 * @var array<int, array>     $family_members
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<label class="jet-form-builder__field-wrap">
    <input
        type="radio"
        name="patient_select"
        id="pat_self"
        value="self"
        class="jet-form-builder__field radio-field"
        checked
    />
    <span class="jet-form-builder__field-label">
        <?php
        echo esc_html(
            sprintf(
                /* translators: %s display name */
                __('עבורי - %s', 'clinic-queue-management'),
                $current_user->display_name
            )
        );
        ?>
    </span>
</label>
<?php foreach ($family_members as $index => $member) : ?>
    <?php
    $member_name = isset($member['first_name'])
        ? (string) $member['first_name']
        : __('בן משפחה', 'clinic-queue-management');
    ?>
    <label class="jet-form-builder__field-wrap">
        <input
            type="radio"
            name="patient_select"
            id="pat_<?php echo esc_attr((string) (int) $index); ?>"
            value="family_<?php echo esc_attr((string) (int) $index); ?>"
            class="jet-form-builder__field radio-field"
        />
        <span class="jet-form-builder__field-label"><?php echo esc_html($member_name); ?></span>
    </label>
<?php endforeach; ?>
