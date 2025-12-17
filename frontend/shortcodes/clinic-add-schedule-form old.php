<?php
/**
 * Add Schedule Form Shortcode
 * 
 * Shortcode: [clinic_add_schedule_form]
 * טופס רב-שלבי להוספת יומן חדש למרפאה.
 * Uses JetFormBuilder classes so styling is inherited.
 */

/**
 * טופס מתוכנן:
 * 1. שלב בחירת פעולה - החלק הישן נשמר בפועל (Google / Clinix).
 * 2. שלב חיבור הרופא:
 *    - dropdown של המרפאות של המשתמש (REST API wp/v2/clinics?author=...&per_page=100).
 *      ניתן להקטין את `per_page` ל־20 במידה שרוצים להגבלה.
 *    - dropdown של רופאים מתוך המרפאה הנבחרת (JetEngine relation id 5 children).
 *    - או שדה טקסט להזנת יומן/מטפל ידנית.
 * 3. שלב הגדרות ימים ושעות עבודה:
 *    - 7 צ'קבוקסים של ימים בשבוע
 *    - רפיטר של from_time + to_time לכל יום מסומן (הוספת פיצול)
 * 4. הגדרת שם ומשך טיפול:
 *    - רפיטר של סוגי טיפולים עם 4 שדות:
 *      1. סוג טיפול (טקסט)
 *      2. תת-תחום (dropdown - ילדים של taxonomy specialities)
 *      3. מחיר (מספר שלם)
 *      4. משך זמן (dropdown - 5 דקות עד 6 שעות בקפיצות של 5)
 * 5. בלחיצה על שמירת הגדרות יומן:
 *    - יצירת פוסט חדש מסוג schedules
 *    - עדכון post_title (יומן של {שם הרופא/manual_calendar_name})
 *    - עדכון schedule_type (clinix/google)
 *    - עדכון repeater fields לכל יום: sunday, monday וכו' עם start_time + end_time
 * 6. במידה הצליח - הצגת מסך הצלחה.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SVG Icons - כל האייקונים של הטופס
 */
// Google Calendar SVG
$svg_google_calendar = '<svg width="73" height="24" viewBox="0 0 73 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	<g clip-path="url(#clip0_5187_28517)">
		<path d="M70.7263 14.6682L72.7515 16.0209C72.0942 16.9909 70.5221 18.6552 67.8041 18.6552C64.429 18.6552 61.9155 16.0388 61.9155 12.7103C61.9155 9.16848 64.4557 6.76562 67.52 6.76562C70.602 6.76562 72.1119 9.22177 72.6005 10.5478L72.867 11.2242L64.9265 14.5169C65.5305 15.7095 66.472 16.3146 67.8041 16.3146C69.1365 16.3146 70.0602 15.6561 70.7263 14.6682ZM64.5002 12.5236L69.8027 10.3165C69.5095 9.57791 68.6392 9.05291 67.5998 9.05291C66.2766 9.05291 64.438 10.2276 64.5002 12.5236Z" fill="#FF302F"/>
		<path d="M58.0874 0.703125H60.6452V18.1103H58.0874V0.703125Z" fill="#20B15A"/>
		<path d="M54.0549 7.22743H56.5241V17.7999C56.5241 22.1871 53.9394 23.9937 50.884 23.9937C48.0063 23.9937 46.2744 22.0537 45.626 20.4786L47.8908 19.5351C48.2993 20.5051 49.2852 21.6533 50.884 21.6533C52.8468 21.6533 54.0549 20.434 54.0549 18.1559V17.3016H53.9661C53.3798 18.0134 52.2607 18.6541 50.8396 18.6541C47.873 18.6541 45.155 16.0644 45.155 12.7273C45.155 9.37215 47.873 6.75586 50.8396 6.75586C52.2519 6.75586 53.3798 7.38772 53.9661 8.08186H54.0549V7.22743ZM54.2324 12.7273C54.2324 10.627 52.838 9.09629 51.0616 9.09629C49.2674 9.09629 47.7575 10.627 47.7575 12.7273C47.7575 14.8007 49.2674 16.3047 51.0616 16.3047C52.8381 16.3137 54.2325 14.8007 54.2325 12.7273" fill="#3686F7"/>
		<path d="M31.1128 12.6821C31.1128 16.1084 28.4483 18.6268 25.1797 18.6268C21.9112 18.6268 19.2466 16.0995 19.2466 12.6821C19.2466 9.23809 21.9112 6.72852 25.1797 6.72852C28.4483 6.72852 31.1128 9.23809 31.1128 12.6821ZM28.5193 12.6821C28.5193 10.5464 26.9737 9.0778 25.1797 9.0778C23.3856 9.0778 21.8401 10.5464 21.8401 12.6821C21.8401 14.8001 23.3856 16.2864 25.1797 16.2864C26.9739 16.2864 28.5193 14.8001 28.5193 12.6821Z" fill="#FF302F"/>
		<path d="M44.0715 12.7103C44.0715 16.1366 41.4069 18.6551 38.1384 18.6551C34.8698 18.6551 32.2053 16.1365 32.2053 12.7103C32.2053 9.26634 34.8698 6.76562 38.1384 6.76562C41.4069 6.76562 44.0715 9.25748 44.0715 12.7103ZM41.469 12.7103C41.469 10.5746 39.9236 9.10605 38.1294 9.10605C36.3352 9.10605 34.7898 10.5746 34.7898 12.7103C34.7898 14.8283 36.3354 16.3146 38.1294 16.3146C39.9325 16.3146 41.469 14.8195 41.469 12.7103Z" fill="#FFBA40"/>
		<path d="M9.49415 16.0471C5.77258 16.0471 2.85943 13.0391 2.85943 9.31025C2.85943 5.58154 5.77258 2.57354 9.49415 2.57354C11.5015 2.57354 12.9669 3.36554 14.0505 4.38011L15.8359 2.5914C14.326 1.14082 12.3098 0.0371094 9.49415 0.0371094C4.39599 0.0372522 0.105957 4.20225 0.105957 9.31025C0.105957 14.4183 4.39599 18.5834 9.49415 18.5834C12.2476 18.5834 14.326 17.6757 15.9514 15.9848C17.6211 14.3117 18.1362 11.9623 18.1362 10.0578C18.1362 9.46154 18.0652 8.84754 17.9853 8.39368H9.49415V10.8677H15.5427C15.3651 12.4163 14.8766 13.4753 14.1572 14.196C13.2867 15.0771 11.9101 16.0471 9.49415 16.0471Z" fill="#3686F7"/>
	</g>
	<defs>
		<clipPath id="clip0_5187_28517">
			<rect width="73" height="24" fill="white"/>
		</clipPath>
	</defs>
</svg>';

// Clinix Logo SVG
$svg_clinix_logo = '<svg width="78" height="24" viewBox="0 0 78 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	<g clip-path="url(#clip0_5187_28413)">
		<path d="M3.29742 24.0003H62.1243C66.5002 24.0003 71.4968 22.4071 74.3767 14.1688L77.3538 5.9619C78.452 3.05452 77.2072 0.601491 74.8643 0.507812H16.0388C11.6635 0.507812 6.66697 2.10101 3.78709 10.3387L0.808595 18.5462C-0.289597 21.4536 0.955154 23.9066 3.29742 24.0003Z" fill="#00ABEE"/>
		<path d="M21.7307 16.2167C21.6448 16.2182 21.56 16.2371 21.4817 16.2723C21.4034 16.3075 21.3332 16.3583 21.2755 16.4214C20.4894 17.1575 19.5195 17.7129 18.2957 17.7129C16.0615 17.7129 14.3642 15.8881 14.3642 13.5924V13.5515C14.3642 11.2765 16.0196 9.45178 18.1923 9.45178C19.4756 9.45178 20.3441 10.0252 21.11 10.7231C21.2561 10.8415 21.4387 10.9067 21.6274 10.9078C21.728 10.9079 21.8276 10.8883 21.9205 10.8502C22.0134 10.8121 22.0978 10.7562 22.1689 10.6857C22.24 10.6152 22.2964 10.5315 22.3347 10.4394C22.3731 10.3473 22.3928 10.2487 22.3926 10.149C22.3929 10.0446 22.3709 9.94137 22.328 9.84601C22.2852 9.75064 22.2225 9.66532 22.1441 9.59564C21.1924 8.73447 19.9923 8.05664 18.2133 8.05664C15.0889 8.05664 12.7297 10.5993 12.7297 13.5917V13.6325C12.7297 16.6055 15.0889 19.1267 18.2133 19.1267C20.0132 19.1267 21.2343 18.4295 22.2481 17.4251C22.3783 17.2936 22.4523 17.1175 22.4547 16.9333C22.4488 16.745 22.3706 16.566 22.2361 16.4329C22.1015 16.2997 21.9208 16.2224 21.7307 16.2167Z" fill="white"/>
		<path d="M26.1384 3.8125C25.9272 3.81707 25.7263 3.90391 25.5793 4.05421C25.4323 4.20451 25.3509 4.40616 25.3529 4.61546V18.1827C25.3508 18.2884 25.3702 18.3934 25.41 18.4915C25.4497 18.5896 25.509 18.6788 25.5842 18.7537C25.6595 18.8286 25.7492 18.8878 25.848 18.9277C25.9468 18.9675 26.0527 18.9872 26.1594 18.9857C26.6146 18.9857 26.9455 18.6371 26.9455 18.1827V4.61211C26.9446 4.40031 26.8593 4.19744 26.7081 4.04767C26.557 3.89791 26.3522 3.81338 26.1384 3.8125Z" fill="white"/>
		<path d="M31.5801 4.11914C31.0628 4.11914 30.6494 4.44701 30.6494 4.95957V5.22722C30.6494 5.71903 31.0628 6.06765 31.5801 6.06765C32.1204 6.06765 32.5324 5.71903 32.5324 5.22722V4.95957C32.5324 4.44701 32.1184 4.11914 31.5801 4.11914Z" fill="white"/>
		<path d="M31.5802 8.17983C31.369 8.18457 31.1682 8.27144 31.0211 8.42168C30.874 8.57193 30.7924 8.77348 30.7941 8.98278V18.184C30.7921 18.29 30.8117 18.3953 30.8517 18.4936C30.8917 18.5919 30.9513 18.6813 31.027 18.7562C31.1026 18.8312 31.1928 18.8903 31.292 18.9299C31.3913 18.9695 31.4976 18.9889 31.6046 18.987C32.0598 18.987 32.3907 18.6383 32.3907 18.184V8.97944C32.3923 8.87372 32.3724 8.76877 32.3321 8.67084C32.2919 8.57291 32.2322 8.484 32.1566 8.40939C32.081 8.33479 31.991 8.27602 31.892 8.23658C31.793 8.19714 31.6869 8.17784 31.5802 8.17983Z" fill="white"/>
		<path d="M41.3468 8.05664C39.505 8.05664 38.3879 8.97937 37.6842 10.1309V8.97937C37.6862 8.87338 37.6666 8.76807 37.6266 8.66975C37.5866 8.57142 37.527 8.48211 37.4513 8.40715C37.3756 8.33218 37.2855 8.27311 37.1862 8.23346C37.087 8.19382 36.9807 8.17442 36.8737 8.17642C36.6625 8.18116 36.4616 8.26803 36.3145 8.41827C36.1674 8.56852 36.0859 8.77007 36.0875 8.97937V18.1839C36.0855 18.2896 36.105 18.3947 36.1448 18.4928C36.1846 18.5909 36.244 18.68 36.3193 18.7549C36.3946 18.8299 36.4843 18.889 36.5832 18.9288C36.682 18.9687 36.7879 18.9884 36.8946 18.9869C37.3499 18.9869 37.6808 18.6383 37.6808 18.1839V12.8128C37.6808 10.8242 39.0465 9.49193 40.8883 9.49193C42.7706 9.49193 43.8472 10.7218 43.8472 12.6897V18.1839C43.8472 18.3932 43.9311 18.5938 44.0804 18.7418C44.2297 18.8897 44.4323 18.9728 44.6435 18.9728C44.8546 18.9728 45.0572 18.8897 45.2065 18.7418C45.3558 18.5938 45.4397 18.3932 45.4397 18.1839V12.3003C45.4431 9.77898 43.9323 8.05664 41.3468 8.05664Z" fill="white"/>
		<path d="M49.7686 4.11914C49.2519 4.11914 48.8379 4.44701 48.8379 4.95957V5.22722C48.8379 5.71903 49.2519 6.06765 49.7686 6.06765C50.3089 6.06765 50.7209 5.71903 50.7209 5.22722V4.95957C50.7209 4.44701 50.3069 4.11914 49.7686 4.11914Z" fill="white"/>
		<path d="M49.7683 8.17978C49.5571 8.18452 49.3564 8.27142 49.2094 8.42168C49.0624 8.57194 48.981 8.77349 48.9828 8.98274V18.1839C48.9807 18.2896 49.0001 18.3946 49.0398 18.4927C49.0796 18.5908 49.1388 18.68 49.2141 18.7549C49.2893 18.8298 49.379 18.889 49.4779 18.9289C49.5767 18.9687 49.6826 18.9885 49.7892 18.9869C50.2445 18.9869 50.5754 18.6383 50.5754 18.1839V8.97939C50.577 8.87394 50.5573 8.76923 50.5173 8.67148C50.4773 8.57373 50.4179 8.48494 50.3427 8.41036C50.2674 8.33578 50.1778 8.27695 50.0791 8.23734C49.9804 8.19773 49.8747 8.17816 49.7683 8.17978Z" fill="white"/>
		<path d="M62.8435 17.6718L59.3673 13.4489L62.6983 9.41002C62.8295 9.27071 62.9034 9.08794 62.9057 8.89747C62.908 8.80264 62.8908 8.70834 62.8553 8.62028C62.8197 8.53221 62.7665 8.45222 62.6988 8.38515C62.6311 8.31808 62.5503 8.26533 62.4614 8.23009C62.3725 8.19485 62.2774 8.17787 62.1816 8.18016C61.8919 8.18016 61.6846 8.3441 61.4981 8.56959L58.4359 12.4238L55.3527 8.54952C55.167 8.32402 54.9597 8.18016 54.6497 8.18016C54.5511 8.1766 54.4529 8.1932 54.3611 8.22893C54.2694 8.26466 54.186 8.31875 54.1163 8.38782C54.0466 8.45689 53.992 8.53946 53.9559 8.63037C53.9199 8.72128 53.9031 8.81859 53.9067 8.9162C53.9067 9.12163 53.9891 9.30563 54.1553 9.49031L57.4451 13.4877L53.9486 17.7561C53.88 17.8225 53.8262 17.9024 53.7906 17.9907C53.755 18.079 53.7384 18.1736 53.7419 18.2686C53.7416 18.363 53.7603 18.4566 53.7968 18.5438C53.8333 18.631 53.8869 18.7102 53.9545 18.7767C54.0221 18.8432 54.1023 18.8958 54.1906 18.9314C54.2788 18.967 54.3734 18.9848 54.4686 18.9839C54.7584 18.9839 54.9651 18.82 55.1515 18.5938L58.3792 14.5349L61.6278 18.6166C61.8142 18.8421 62.0209 18.9859 62.3316 18.9859C62.4297 18.9878 62.5271 18.97 62.6181 18.9336C62.709 18.8973 62.7917 18.8431 62.861 18.7744C62.9304 18.7057 62.985 18.6238 63.0217 18.5337C63.0584 18.4436 63.0764 18.3471 63.0745 18.2499C63.0739 18.0405 62.9908 17.8565 62.8435 17.6718Z" fill="white"/>
	</g>
	<defs>
		<clipPath id="clip0_5187_28413">
			<rect width="78" height="24" fill="white"/>
		</clipPath>
	</defs>
</svg>';

// Calendar Icon SVG (for success screen)
$svg_calendar_icon = '<svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
	<path d="M10 60C10 41.1438 10 31.7157 15.8579 25.8579C21.7157 20 31.1438 20 50 20H70C88.8562 20 98.2843 20 104.142 25.8579C110 31.7157 110 41.1438 110 60V70C110 88.8562 110 98.2843 104.142 104.142C98.2843 110 88.8562 110 70 110H50C31.1438 110 21.7157 110 15.8579 104.142C10 98.2843 10 88.8562 10 70V60Z" stroke="#00A3A3" stroke-width="2"/>
	<path d="M35 20V12.5" stroke="#00A3A3" stroke-width="2" stroke-linecap="round"/>
	<path d="M85 20V12.5" stroke="#00A3A3" stroke-width="2" stroke-linecap="round"/>
	<path d="M12.5 45H107.5" stroke="#00A3A3" stroke-width="2" stroke-linecap="round"/>
	<path d="M90 85C90 87.7614 87.7614 90 85 90C82.2386 90 80 87.7614 80 85C80 82.2386 82.2386 80 85 80C87.7614 80 90 82.2386 90 85Z" fill="#00A3A3"/>
	<path d="M90 65C90 67.7614 87.7614 70 85 70C82.2386 70 80 67.7614 80 65C80 62.2386 82.2386 60 85 60C87.7614 60 90 62.2386 90 65Z" fill="#00A3A3"/>
	<path d="M65 85C65 87.7614 62.7614 90 60 90C57.2386 90 55 87.7614 55 85C55 82.2386 57.2386 80 60 80C62.7614 80 65 82.2386 65 85Z" fill="#00A3A3"/>
	<path d="M65 65C65 67.7614 62.7614 70 60 70C57.2386 70 55 67.7614 55 65C55 62.2386 57.2386 60 60 60C62.7614 60 65 62.2386 65 65Z" fill="#00A3A3"/>
	<path d="M40 85C40 87.7614 37.7614 90 35 90C32.2386 90 30 87.7614 30 85C30 82.2386 32.2386 80 35 80C37.7614 80 40 82.2386 40 85Z" fill="#00A3A3"/>
	<path d="M40 65C40 67.7614 37.7614 70 35 70C32.2386 70 30 67.7614 30 65C30 62.2386 32.2386 60 35 60C37.7614 60 40 62.2386 40 65Z" fill="#00A3A3"/>
</svg>';

/**
 * Helper function: Generate time options for select dropdown
 * 
 * @param string $default_time Default selected time
 * @return string HTML options
 */
function clinic_generate_time_options($default_time = '08:00') {
	$options = '';
	for($h = 0; $h < 24; $h++) { 
		for($m = 0; $m < 60; $m+=30) {
			$time = sprintf('%02d:%02d', $h, $m);
			$selected = ($time === $default_time) ? ' selected' : '';
			$options .= "<option value=\"{$time}\"{$selected}>{$time}</option>";
		}
	}
	return $options;
}

/**
 * Helper function: Generate a single day time range HTML
 * 
 * @param string $day_key Day key (e.g., 'sunday')
 * @param string $day_label Day label in Hebrew
 * @param string $default_end Default end time (default: '18:00', except Friday: '16:00')
 * @return string HTML for day time range
 */
function clinic_generate_day_time_range($day_key, $day_label, $default_end = '18:00') {
	ob_start();
	?>
	<div class="day-time-range" data-day="<?php echo esc_attr($day_key); ?>" style="display:none;">
		<div class="day-time-header">
			<h4><?php echo esc_html($day_label); ?></h4>
			<button type="button" class="add-time-split-btn" data-day="<?php echo esc_attr($day_key); ?>">
				<span>+</span> הוספת פיצול
			</button>
		</div>
		<div class="time-ranges-list" data-day="<?php echo esc_attr($day_key); ?>">
			<div class="time-range-row">
				<label>מ-:</label>
				<select class="time-select from-time" name="<?php echo esc_attr($day_key); ?>_from_time[]">
					<?php echo clinic_generate_time_options('08:00'); ?>
				</select>
				<label>עד-:</label>
				<select class="time-select to-time" name="<?php echo esc_attr($day_key); ?>_to_time[]">
					<?php echo clinic_generate_time_options($default_end); ?>
				</select>
				<button type="button" class="remove-time-split-btn" style="display:none;">🗑️</button>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Helper function: Generate treatment duration options
 * 
 * @param int $default_minutes Default selected duration in minutes
 * @return string HTML options
 */
function clinic_generate_duration_options($default_minutes = 45) {
	$options = '';
	// 5 minutes to 6 hours in 5-minute increments
	for($minutes = 5; $minutes <= 360; $minutes += 5) {
		$hours = floor($minutes / 60);
		$mins = $minutes % 60;
		if ($hours > 0 && $mins > 0) {
			$label = $hours . ' שעות ו-' . $mins . ' דקות';
		} elseif ($hours > 0) {
			$label = $hours . ' שע' . ($hours == 1 ? 'ה' : 'ות');
		} else {
			$label = $mins . ' דקות';
		}
		$selected = ($minutes == $default_minutes) ? ' selected' : '';
		$options .= "<option value=\"{$minutes}\"{$selected}>{$label}</option>";
	}
	return $options;
}

/**
 * Register the add schedule form shortcode
 */
add_shortcode('clinic_add_schedule_form', function () {
	// Access SVG variables
	global $svg_google_calendar, $svg_clinix_logo, $svg_calendar_icon;
	
	// Define days of week
	$days_of_week = array(
		'sunday' => 'יום ראשון',
		'monday' => 'יום שני',
		'tuesday' => 'יום שלישי',
		'wednesday' => 'יום רביעי',
		'thursday' => 'יום חמישי',
		'friday' => 'יום שישי',
		'saturday' => 'יום שבת'
	);
	
	ob_start();
	?>
	<style>
		.clinic-add-schedule-form {
			/* CSS Custom Properties */
			--color-primary: #4f8bff;
			--color-primary-hover: #3d7ae8;
			--color-text-primary: #0c1c4a;
			--color-text-secondary: #6b7280;
			--color-text-muted: #9ca3af;
			--color-border: #dbe1e8;
			--color-border-light: #e5e7eb;
			--color-bg: #fff;
			--color-bg-disabled: #f2f4f7;
			--color-bg-separator: #ffe6ed;
			--color-separator-text: #d94668;
			
			--spacing-xs: clamp(0.5rem, 0.4vw + 0.4rem, 0.625rem);
			--spacing-sm: clamp(0.75rem, 0.6vw + 0.6rem, 0.875rem);
			--spacing-md: clamp(1rem, 0.8vw + 0.8rem, 1.25rem);
			--spacing-lg: clamp(1.25rem, 1.2vw + 1rem, 1.5rem);
			--spacing-xl: clamp(1.5rem, 1.6vw + 1.2rem, 2rem);
			
			--radius-sm: 10px;
			--radius-md: 16px;
			--radius-lg: 18px;
			
			--shadow-sm: 0 8px 24px rgba(0, 0, 0, 0.06);
			--shadow-md: 0 10px 30px rgba(0, 0, 0, 0.08);
			--shadow-lg: 0 14px 34px rgba(79, 139, 255, 0.22);
			
			--transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
			--transition-smooth: 300ms cubic-bezier(0.4, 0, 0.2, 1);
			
			--font-size-sm: clamp(0.875rem, 0.8vw + 0.7rem, 1rem);
			--font-size-base: clamp(0.9375rem, 0.9vw + 0.8rem, 1.0625rem);
			--font-size-lg: clamp(1.125rem, 1.2vw + 1rem, 1.25rem);
			--font-size-xl: clamp(1.25rem, 1.4vw + 1.1rem, 1.5rem);
			--font-size-2xl: clamp(1.5rem, 2vw + 1.3rem, 1.75rem);
			
			/* Layout */
			direction: rtl;
			max-width: min(900px, 100% - var(--spacing-xl));
			margin-inline: auto;
			container-type: inline-size;
		}
		
		.clinic-add-schedule-form .step {
			display: none;
		}
		
		.clinic-add-schedule-form .step.is-active {
			display: block;
			animation: fadeIn var(--transition-smooth) ease-in-out;
		}
		
		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(8px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.clinic-add-schedule-form .action-cards {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr));
			gap: var(--spacing-md);
		}
		
		.clinic-add-schedule-form .action-card {
			position: relative;
			display: flex;
			flex-direction: column;
			align-items: center;
			text-align: center;
			
			border: 1px solid var(--color-border);
			border-radius: var(--radius-lg);
			background: var(--color-bg);
			padding: var(--spacing-lg) var(--spacing-md);
			
			box-shadow: var(--shadow-sm);
			transition: 
				box-shadow var(--transition-base),
				border-color var(--transition-base),
				transform var(--transition-base);
			
			cursor: pointer;
			min-height: 180px;
			will-change: transform, box-shadow;
		}
		
		.clinic-add-schedule-form .action-card:hover {
			box-shadow: var(--shadow-md);
			transform: translateY(-2px);
		}
		
		.clinic-add-schedule-form .action-card.is-active {
			border-color: var(--color-primary);
			box-shadow: var(--shadow-lg);
		}
		
		.clinic-add-schedule-form .action-card input[type="radio"] {
			position: absolute;
			inset: 0;
			opacity: 0;
			cursor: pointer;
			z-index: 1;
		}
		
		.clinic-add-schedule-form .action-card svg {
			max-width: 90px;
			height: auto;
			aspect-ratio: auto;
		}
		
		.clinic-add-schedule-form .card-title {
			font-size: var(--font-size-xl);
			font-weight: 700;
			color: var(--color-text-primary);
			margin-block: var(--spacing-xs) var(--spacing-sm);
		}
		
		.clinic-add-schedule-form .card-desc {
			color: var(--color-text-secondary);
			font-size: var(--font-size-sm);
			margin-block-start: var(--spacing-sm);
		}
		
		.clinic-add-schedule-form .continue-wrap {
			text-align: center;
			margin-block-start: var(--spacing-xl);
		}
		
		.clinic-add-schedule-form .continue-btn {
			min-width: max(160px, 20%);
		}
		
		.clinic-add-schedule-form .google-step .jet-form-builder__field-wrap {
			position: relative;
		}
		
		.clinic-add-schedule-form .google-step .jet-form-builder__field {
			width: 100%;
			padding: var(--spacing-sm) var(--spacing-md);
			border-radius: var(--radius-md);
			border: 1px solid var(--color-border);
			box-shadow: var(--shadow-sm);
			font-size: var(--font-size-base);
			transition: 
				border-color var(--transition-base),
				box-shadow var(--transition-base);
		}
		
		.clinic-add-schedule-form .google-step .jet-form-builder__field:focus-visible {
			outline: 2px solid var(--color-primary);
			outline-offset: 2px;
			border-color: var(--color-primary);
		}
		
		.clinic-add-schedule-form .google-step .jet-form-builder__field:disabled {
			background: var(--color-bg-disabled);
			opacity: 0.9;
			cursor: not-allowed;
		}
		
		.clinic-add-schedule-form .google-step .select-field {
			appearance: none;
			background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: left var(--spacing-md) center;
			padding-inline-start: 2.5rem;
		}
		
		.clinic-add-schedule-form .google-step .separator {
			display: flex;
			align-items: center;
			gap: var(--spacing-sm);
			margin-block: var(--spacing-lg);
			color: var(--color-border-light);
		}
		
		.clinic-add-schedule-form .google-step .separator::before,
		.clinic-add-schedule-form .google-step .separator::after {
			content: "";
			flex: 1;
			height: 1px;
			background: var(--color-border-light);
		}
		
		.clinic-add-schedule-form .google-step .separator span {
			padding: var(--spacing-xs) var(--spacing-sm);
			background: var(--color-bg-separator);
			color: var(--color-separator-text);
			border-radius: var(--radius-sm);
			font-weight: 700;
			font-size: var(--font-size-sm);
		}
		
		.clinic-add-schedule-form .google-step .helper-text {
			font-size: var(--font-size-base);
			color: var(--color-text-primary);
			font-weight: 700;
			margin-block-end: var(--spacing-sm);
		}
		
		.clinic-add-schedule-form .google-step .icon-search {
			position: absolute;
			inset-inline-start: var(--spacing-sm);
			inset-block-start: 50%;
			transform: translateY(-50%);
			pointer-events: none;
			color: var(--color-text-muted);
		}
		
		/* Schedule Settings Step (Step 3) */
		.clinic-add-schedule-form .schedule-settings-step {
			animation: fadeIn var(--transition-smooth) ease-in-out;
		}
		
		.clinic-add-schedule-form .days-checkboxes-container {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: var(--spacing-md);
			margin-block: var(--spacing-lg);
		}
		
		.clinic-add-schedule-form .day-checkbox {
			display: flex;
			align-items: center;
			gap: var(--spacing-sm);
			padding: var(--spacing-md);
			border: 2px solid var(--color-border);
			border-radius: var(--radius-md);
			cursor: pointer;
			transition: all var(--transition-base);
			background: var(--color-bg);
		}
		
		.clinic-add-schedule-form .day-checkbox:hover {
			border-color: var(--color-primary);
			background: rgba(79, 139, 255, 0.05);
		}
		
		.clinic-add-schedule-form .day-checkbox input[type="checkbox"] {
			width: 20px;
			height: 20px;
			cursor: pointer;
			accent-color: var(--color-primary);
		}
		
		.clinic-add-schedule-form .day-checkbox span {
			font-size: var(--font-size-base);
			font-weight: 600;
			color: var(--color-text-primary);
		}
		
		.clinic-add-schedule-form .day-checkbox input[type="checkbox"]:checked + span {
			color: var(--color-primary);
		}
		
		.clinic-add-schedule-form .days-time-ranges {
			margin-block: var(--spacing-lg);
		}
		
		.clinic-add-schedule-form .day-time-range {
			margin-block-end: var(--spacing-lg);
			padding: var(--spacing-lg);
			border: 1px solid var(--color-border);
			border-radius: var(--radius-md);
			background: var(--color-bg);
			box-shadow: var(--shadow-sm);
		}
		
		.clinic-add-schedule-form .day-time-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-block-end: var(--spacing-md);
			padding-block-end: var(--spacing-sm);
			border-block-end: 1px solid var(--color-border-light);
		}
		
		.clinic-add-schedule-form .day-time-header h4 {
			margin: 0;
			font-size: var(--font-size-lg);
			font-weight: 700;
			color: var(--color-text-primary);
		}
		
		.clinic-add-schedule-form .add-time-split-btn {
			display: inline-flex;
			align-items: center;
			gap: var(--spacing-xs);
			padding: var(--spacing-xs) var(--spacing-md);
			border: 1px solid var(--color-primary);
			border-radius: var(--radius-sm);
			background: transparent;
			color: var(--color-primary);
			font-size: var(--font-size-sm);
			font-weight: 600;
			cursor: pointer;
			transition: all var(--transition-base);
		}
		
		.clinic-add-schedule-form .add-time-split-btn:hover {
			background: var(--color-primary);
			color: white;
		}
		
		.clinic-add-schedule-form .time-range-row {
			display: grid;
			grid-template-columns: auto 1fr auto 1fr auto;
			align-items: center;
			gap: var(--spacing-sm);
			margin-block-end: var(--spacing-sm);
			padding: var(--spacing-sm);
			border-radius: var(--radius-sm);
			background: rgba(0, 0, 0, 0.02);
		}
		
		.clinic-add-schedule-form .time-range-row label {
			font-size: var(--font-size-sm);
			font-weight: 600;
			color: var(--color-text-secondary);
		}
		
		.clinic-add-schedule-form .time-select {
			padding: var(--spacing-xs) var(--spacing-sm);
			border: 1px solid var(--color-border);
			border-radius: var(--radius-sm);
			background: var(--color-bg);
			font-size: var(--font-size-sm);
			cursor: pointer;
		}
		
		.clinic-add-schedule-form .remove-time-split-btn {
			padding: var(--spacing-xs);
			border: none;
			background: transparent;
			font-size: 1.2rem;
			cursor: pointer;
			opacity: 0.6;
			transition: opacity var(--transition-base);
		}
		
		.clinic-add-schedule-form .remove-time-split-btn:hover {
			opacity: 1;
		}
		
		/* Treatment Types Repeater */
		.clinic-add-schedule-form .treatments-repeater {
			margin-block: var(--spacing-lg);
		}
		
		.clinic-add-schedule-form .treatment-row {
			display: grid;
			grid-template-columns: repeat(4, 1fr) auto;
			gap: var(--spacing-md);
			margin-block-end: var(--spacing-md);
			padding: var(--spacing-md);
			border: 1px solid var(--color-border);
			border-radius: var(--radius-md);
			background: var(--color-bg);
			box-shadow: var(--shadow-sm);
		}
		
		.clinic-add-schedule-form .treatment-field {
			display: flex;
			flex-direction: column;
			gap: var(--spacing-xs);
		}
		
		.clinic-add-schedule-form .treatment-field label {
			font-size: var(--font-size-sm);
			font-weight: 600;
			color: var(--color-text-secondary);
		}
		
		.clinic-add-schedule-form .treatment-field input,
		.clinic-add-schedule-form .treatment-field select {
			padding: var(--spacing-sm);
			border: 1px solid var(--color-border);
			border-radius: var(--radius-sm);
			font-size: var(--font-size-base);
		}
		
		.clinic-add-schedule-form .remove-treatment-btn {
			align-self: center;
			padding: var(--spacing-xs);
			border: none;
			background: transparent;
			font-size: 1.5rem;
			cursor: pointer;
			opacity: 0.6;
			transition: opacity var(--transition-base);
		}
		
		.clinic-add-schedule-form .remove-treatment-btn:hover {
			opacity: 1;
		}
		
		.clinic-add-schedule-form .add-treatment-btn {
			display: inline-flex;
			align-items: center;
			gap: var(--spacing-xs);
			padding: var(--spacing-sm) var(--spacing-lg);
			border: 2px dashed var(--color-border);
			border-radius: var(--radius-md);
			background: transparent;
			color: var(--color-text-secondary);
			font-size: var(--font-size-base);
			font-weight: 600;
			cursor: pointer;
			transition: all var(--transition-base);
			margin-block: var(--spacing-md);
		}
		
		.clinic-add-schedule-form .add-treatment-btn:hover {
			border-color: var(--color-primary);
			color: var(--color-primary);
			background: rgba(79, 139, 255, 0.05);
		}
		
		.clinic-add-schedule-form .save-schedule-btn {
			width: 100%;
			max-width: 400px;
			margin: var(--spacing-xl) auto 0;
			display: block;
			padding: var(--spacing-md) var(--spacing-xl);
			background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
			border: none;
			border-radius: var(--radius-md);
			color: white;
			font-size: var(--font-size-lg);
			font-weight: 700;
			cursor: pointer;
			box-shadow: var(--shadow-md);
			transition: all var(--transition-base);
		}
		
		.clinic-add-schedule-form .save-schedule-btn:hover {
			box-shadow: var(--shadow-lg);
			transform: translateY(-2px);
		}
		
		/* Success Screen */
		.clinic-add-schedule-form .success-step {
			text-align: center;
			padding: var(--spacing-xl);
		}
		
		.clinic-add-schedule-form .success-content {
			max-width: 600px;
			margin: 0 auto;
		}
		
		.clinic-add-schedule-form .success-icon {
			margin-block-end: var(--spacing-lg);
			animation: scaleIn 0.5s ease-out;
		}
		
		@keyframes scaleIn {
			from {
				opacity: 0;
				transform: scale(0.5);
			}
			to {
				opacity: 1;
				transform: scale(1);
			}
		}
		
		.clinic-add-schedule-form .success-icon svg {
			width: 120px;
			height: 120px;
			filter: drop-shadow(0 4px 12px rgba(0, 163, 163, 0.3));
		}
		
		.clinic-add-schedule-form .success-title {
			font-size: var(--font-size-2xl);
			font-weight: 800;
			color: var(--color-text-primary);
			margin-block: var(--spacing-md);
		}
		
		.clinic-add-schedule-form .success-subtitle {
			font-size: var(--font-size-base);
			color: var(--color-text-secondary);
			margin-block-end: var(--spacing-xl);
		}
		
		.clinic-add-schedule-form .success-schedule-summary {
			padding: var(--spacing-lg);
			border: 1px solid var(--color-border);
			border-radius: var(--radius-md);
			background: rgba(0, 163, 163, 0.05);
			margin-block-end: var(--spacing-xl);
		}
		
		.clinic-add-schedule-form .success-schedule-summary h3 {
			font-size: var(--font-size-lg);
			font-weight: 700;
			color: var(--color-text-primary);
			margin-block-end: var(--spacing-md);
		}
		
		.clinic-add-schedule-form .schedule-days-list {
			text-align: right;
			font-size: var(--font-size-base);
			color: var(--color-text-secondary);
			line-height: 1.8;
		}
		
		.clinic-add-schedule-form .success-actions {
			display: flex;
			flex-direction: column;
			gap: var(--spacing-md);
			align-items: center;
		}
		
		.clinic-add-schedule-form .sync-google-btn {
			width: 100%;
			max-width: 300px;
		}
		
		.clinic-add-schedule-form .transfer-request-link {
			color: var(--color-primary);
			text-decoration: underline;
			font-size: var(--font-size-base);
			transition: color var(--transition-base);
		}
		
		.clinic-add-schedule-form .transfer-request-link:hover {
			color: var(--color-primary-hover);
		}
		
		/* Responsive adjustments */
		@container (max-width: 600px) {
			.clinic-add-schedule-form .action-cards {
				grid-template-columns: 1fr;
			}
			
			.clinic-add-schedule-form .action-card {
				min-height: auto;
				padding: var(--spacing-md);
			}
		}
		
		@media (prefers-reduced-motion: reduce) {
			.clinic-add-schedule-form * {
				animation-duration: 0.01ms !important;
				animation-iteration-count: 1 !important;
				transition-duration: 0.01ms !important;
			}
		}
	</style>

	<div class="jet-form-builder jet-form-builder--default clinic-add-schedule-form">
		<div class="step step-start is-active" data-step="start">
			<div class="jet-form-builder__row field-type-heading is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text" style="font-size:28px;font-weight:800;color:#0c1c4a;">איזה פעולה תרצו לעשות</div>
				</div>
			</div>

			<div class="jet-form-builder__row field-type-radio-field action-cards">
				<label class="jet-form-builder__field-wrap action-card" data-value="google">
					<input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="google">
					<div class="card-title">חיבור יומן</div>
					<div aria-hidden="true">
						<?php echo $svg_google_calendar; ?>
					</div>
					<div class="card-desc">Lorem ipsum dolor sit amet consectetur.</div>
				</label>

				<label class="jet-form-builder__field-wrap action-card" data-value="clinix">
					<input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="clinix">
					<div class="card-title">הוספת יומן</div>
					<div aria-hidden="true">
						<?php echo $svg_clinix_logo; ?>
					</div>
					<div class="card-desc">Lorem ipsum dolor sit amet consectetur.</div>
				</label>
			</div>

			<div class="jet-form-builder__row field-type-submit-field continue-wrap">
				<div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
					<button type="button" class="jet-form-builder__action-button jet-form-builder__submit continue-btn" disabled>המשך</button>
				</div>
			</div>
		</div>

		<div class="step google-step" data-step="google" aria-hidden="true">
			<div class="jet-form-builder__row field-type-heading is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">חיבור יומן רופא חדש</div>
				</div>
			</div>

			<div class="jet-form-builder__row field-type-select-field is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text helper-text">בחר מרפאה</div>
				</div>
				<div class="jet-form-builder__field-wrap">
					<select class="jet-form-builder__field select-field clinic-select" name="clinic_id">
						<option value="">טוען מרפאות...</option>
					</select>
				</div>
			</div>

			<div class="jet-form-builder__row field-type-select-field is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text helper-text">בחר רופא מתוך רשימת אנשי צוות בפורטל</div>
				</div>
				<div class="jet-form-builder__field-wrap">
					<select class="jet-form-builder__field select-field doctor-select" name="doctor_id" disabled>
						<option value="">בחר מרפאה תחילה</option>
					</select>
				</div>
			</div>

			<div class="separator" aria-hidden="true"><span>או</span></div>

			<div class="jet-form-builder__row field-type-text-field is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text helper-text">חיבור יומן שלא נמצא בפורטל</div>
				</div>
				<div class="jet-form-builder__field-wrap">
					<input type="text" class="jet-form-builder__field text-field manual-calendar" name="manual_calendar_name" placeholder="שם היומן/המטפל">
				</div>
			</div>

			<div class="jet-form-builder__row field-type-submit-field continue-wrap">
				<div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
					<button type="button" class="jet-form-builder__action-button jet-form-builder__submit continue-btn continue-btn-google" disabled>המשך</button>
				</div>
			</div>
		</div>

		<!-- Step 3: Schedule Settings -->
		<div class="step schedule-settings-step" data-step="schedule-settings" aria-hidden="true">
			<div class="jet-form-builder__row field-type-heading is-filled">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת ימים ושעות עבודה</div>
				</div>
			</div>

			<!-- Days of Week Checkboxes -->
			<div class="days-checkboxes-container">
				<label class="day-checkbox">
					<input type="checkbox" name="day_sunday" value="sunday" data-day="sunday">
					<span>יום ראשון</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_monday" value="monday" data-day="monday">
					<span>יום שני</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_tuesday" value="tuesday" data-day="tuesday">
					<span>יום שלישי</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_wednesday" value="wednesday" data-day="wednesday">
					<span>יום רביעי</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_thursday" value="thursday" data-day="thursday">
					<span>יום חמישי</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_friday" value="friday" data-day="friday">
					<span>יום שישי</span>
				</label>
				<label class="day-checkbox">
					<input type="checkbox" name="day_saturday" value="saturday" data-day="saturday">
					<span>יום שבת</span>
				</label>
			</div>

			<!-- Time Ranges for each day (will be shown dynamically) -->
			<div class="days-time-ranges">
				<?php 
				// Generate day time ranges using the helper function
				foreach ($days_of_week as $day_key => $day_label) {
					// Friday has different default end time
					$default_end = ($day_key === 'friday') ? '16:00' : '18:00';
					echo clinic_generate_day_time_range($day_key, $day_label, $default_end);
				}
				?>
			</div>

			<!-- Treatment Types Section -->
			<div class="jet-form-builder__row field-type-heading is-filled" style="margin-top:2rem;">
				<div class="jet-form-builder__label">
					<div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת שם ומשך טיפול</div>
				</div>
			</div>

			<!-- Treatment Types Repeater -->
			<div class="treatments-repeater">
				<div class="treatment-row">
					<div class="treatment-field">
						<label>סוג טיפול</label>
						<input type="text" class="jet-form-builder__field" name="treatment_name[]" placeholder="שם הטיפול">
					</div>
					<div class="treatment-field">
						<label>תת-תחום</label>
						<select class="jet-form-builder__field select-field subspeciality-select" name="treatment_subspeciality[]">
							<option value="">בחר תת-תחום</option>
						</select>
					</div>
					<div class="treatment-field">
						<label>מחיר</label>
						<input type="number" class="jet-form-builder__field" name="treatment_price[]" placeholder="150" min="0" step="1">
					</div>
					<div class="treatment-field">
						<label>משך זמן</label>
						<select class="jet-form-builder__field select-field" name="treatment_duration[]">
							<?php echo clinic_generate_duration_options(45); ?>
						</select>
					</div>
					<button type="button" class="remove-treatment-btn" style="display:none;">🗑️</button>
				</div>
			</div>

			<button type="button" class="add-treatment-btn">
				<span>+</span> הוספת טיפול
			</button>

			<!-- Submit Button -->
			<div class="jet-form-builder__row field-type-submit-field continue-wrap">
				<div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
					<button type="button" class="jet-form-builder__action-button jet-form-builder__submit save-schedule-btn">שמירת הגדרות יומן</button>
				</div>
			</div>
		</div>

		<!-- Success Screen -->
		<div class="step success-step" data-step="success" aria-hidden="true" style="display:none;">
			<div class="success-content">
				<div class="success-icon">
					<?php echo $svg_calendar_icon; ?>
				</div>
				<h2 class="success-title">היומן הוגדר בהצלחה!</h2>
				<p class="success-subtitle">נא לחבר את היומן מתוך יומן גוגל</p>
				
				<div class="success-schedule-summary">
					<h3>ימי עבודה</h3>
					<div class="schedule-days-list">
						<!-- Will be populated by JavaScript -->
					</div>
				</div>

				<div class="success-actions">
					<button type="button" class="jet-form-builder__action-button jet-form-builder__submit sync-google-btn">
						סנכרון יומן מגוגל
					</button>
					<a href="#" class="transfer-request-link">העבר בקשת סנכרון לכרטיס רופא</a>
				</div>
			</div>
		</div>
	</div>

	<script>
		(function() {
			const root = document.currentScript.closest('.clinic-add-schedule-form') || document.querySelector('.clinic-add-schedule-form');
			if (!root) return;
			const cards = root.querySelectorAll('.action-card');
			const button = root.querySelector('.continue-btn');
			const stepStart = root.querySelector('.step-start');
			const stepGoogle = root.querySelector('.google-step');
			const googleNextBtn = root.querySelector('.continue-btn-google');
			const clinicSelect = root.querySelector('.clinic-select');
			const doctorSelect = root.querySelector('.doctor-select');
			const manualCalendar = root.querySelector('.manual-calendar');

			function setActive(value) {
				cards.forEach((card) => {
					const input = card.querySelector('input[type="radio"]');
					const isActive = input && input.value === value;
					card.classList.toggle('is-active', isActive);
					if (isActive && input) input.checked = true;
				});
				if (button) {
					button.disabled = !value;
					button.classList.toggle('is-disabled', !value);
				}
			}

			cards.forEach((card) => {
				card.addEventListener('click', (e) => {
					const value = card.dataset.value || '';
					setActive(value);
					root.dispatchEvent(new CustomEvent('jet-multi-step:select', { detail: { value }, bubbles: true }));
				});
			});

			if (button) {
				button.addEventListener('click', () => {
					const selected = root.querySelector('input[name="jet_action_choice"]:checked');
					const value = selected ? selected.value : '';
					if (!value) return;
					if (value === 'google' && stepGoogle && stepStart) {
						stepStart.classList.remove('is-active');
						stepGoogle.classList.add('is-active');
						stepGoogle.removeAttribute('aria-hidden');
						// Load clinics when entering Google step
						loadClinics();
					}
					root.dispatchEvent(new CustomEvent('jet-multi-step:next', { detail: { value, step: value }, bubbles: true }));
				});
			}

            const clinicsEndpoint = '<?php echo esc_url(rest_url('wp/v2/clinics?per_page=30&author=' . get_current_user_id() . '')); ?>';

            // Load user's clinics on step change
            async function loadClinics() {
                if (!clinicSelect) return;
                
                try {
                    const response = await fetch(clinicsEndpoint, {
                        headers: {
                            'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Failed to load clinics');
                    
                    const clinics = await response.json();
                    clinicSelect.innerHTML = '<option value="">בחר מרפאה</option>';
                    
                    if (clinics && clinics.length > 0) {
                        clinics.forEach(clinic => {
                            const option = document.createElement('option');
                            option.value = clinic.id;
                            option.textContent = clinic.title.rendered || clinic.title || clinic.name;
                            clinicSelect.appendChild(option);
                        });
                        clinicSelect.disabled = false;
                    } else {
                        clinicSelect.innerHTML = '<option value="">לא נמצאו מרפאות</option>';
                    }
                } catch (error) {
                    console.error('Error loading clinics:', error);
                    clinicSelect.innerHTML = '<option value="">שגיאה בטעינת מרפאות</option>';
                }
            }

			// Helper function to load doctors one by one if batch fails
			async function loadDoctorsIndividually(ids) {
				if (!doctorSelect) return;
				
				const loadedDoctors = [];
				
				for (const doctorId of ids) {
					try {
						const doctorUrl = `<?php echo esc_url(rest_url('wp/v2/doctors/')); ?>${doctorId}?_fields=id,title`;
						const response = await fetch(doctorUrl, {
							headers: {
								'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
							}
						});
						
						if (response.ok) {
							const doctor = await response.json();
							loadedDoctors.push(doctor);
						}
					} catch (err) {
						console.error(`Error loading doctor ${doctorId}:`, err);
					}
				}
				
				if (loadedDoctors.length > 0) {
					loadedDoctors.forEach(doctor => {
						const option = document.createElement('option');
						option.value = doctor.id;
						
						let doctorName = '';
						if (doctor.title && doctor.title.rendered) {
							doctorName = doctor.title.rendered;
						} else if (doctor.title && typeof doctor.title === 'string') {
							doctorName = doctor.title;
						} else if (doctor.name) {
							doctorName = doctor.name;
						} else if (doctor.post_title) {
							doctorName = doctor.post_title;
						} else {
							doctorName = `רופא #${doctor.id}`;
						}
						
						option.textContent = doctorName;
						doctorSelect.appendChild(option);
					});
					doctorSelect.disabled = false;
				} else {
					// Final fallback: use IDs directly
					ids.forEach(doctorId => {
						const option = document.createElement('option');
						option.value = doctorId;
						option.textContent = `רופא #${doctorId}`;
						doctorSelect.appendChild(option);
					});
					doctorSelect.disabled = false;
				}
			}

			// Load doctors for selected clinic
			async function loadDoctors(clinicId) {
				if (!doctorSelect || !clinicId) return;
				
				doctorSelect.disabled = true;
				doctorSelect.innerHTML = '<option value="">טוען רופאים...</option>';
				
				try {
					// Use relation 5 (Many to many: מרפאות <-> רופאים)
					const relationId = 5;
					const response = await fetch(`<?php echo esc_url(home_url('/wp-json/jet-rel/')); ?>${relationId}/children/${clinicId}`);
					
					if (!response.ok) {
						throw new Error(`Failed to load doctors from relation ${relationId}: ${response.status} ${response.statusText}`);
					}
					
					const relationData = await response.json();
					
					// Extract doctor IDs from relation response
					// Response format: [{"child_object_id": "123"}, {"child_object_id": "456"}]
					const doctorIds = [];
					if (Array.isArray(relationData) && relationData.length > 0) {
						relationData.forEach(item => {
							if (item.child_object_id) {
								doctorIds.push(item.child_object_id);
							}
						});
					}
					
					doctorSelect.innerHTML = '<option value="">בחר רופא</option>';
					
					if (doctorIds.length > 0) {
						// Fetch doctor details from REST API
						const doctorIdsParam = doctorIds.join(',');
						const doctorsUrl = `<?php echo esc_url(rest_url('wp/v2/doctors')); ?>?include=${doctorIdsParam}&per_page=100&_fields=id,title`;
						
						const doctorsResponse = await fetch(doctorsUrl, {
							headers: {
								'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
							}
						});
						
						if (doctorsResponse.ok) {
							const doctors = await doctorsResponse.json();
							
							// Debug: log the response to see what we're getting
							if (window.console && console.log) {
								console.log('Doctors API response:', doctors);
							}
							
							if (doctors && doctors.length > 0) {
								doctors.forEach(doctor => {
									const option = document.createElement('option');
									option.value = doctor.id;
									
									// Try multiple ways to get the doctor name
									let doctorName = '';
									if (doctor.title && doctor.title.rendered) {
										doctorName = doctor.title.rendered;
									} else if (doctor.title && typeof doctor.title === 'string') {
										doctorName = doctor.title;
									} else if (doctor.name) {
										doctorName = doctor.name;
									} else if (doctor.post_title) {
										doctorName = doctor.post_title;
									} else {
										doctorName = `רופא #${doctor.id}`;
									}
									
									option.textContent = doctorName;
									doctorSelect.appendChild(option);
								});
								doctorSelect.disabled = false;
							} else {
								// If no doctors returned, try fetching each one individually
								console.warn('No doctors returned from batch request, trying individual requests');
								await loadDoctorsIndividually(doctorIds);
							}
						} else {
							// If REST API fails, try fetching each doctor individually
							const responseText = await doctorsResponse.text();
							console.error('Doctors API error:', doctorsResponse.status, responseText);
							await loadDoctorsIndividually(doctorIds);
						}
					} else {
						doctorSelect.innerHTML = '<option value="">לא נמצאו רופאים במרפאה זו</option>';
					}
				} catch (error) {
					console.error('Error loading doctors:', error);
					doctorSelect.innerHTML = '<option value="">שגיאה בטעינת רופאים</option>';
				}
			}

			// Clinic selection change
			if (clinicSelect) {
				clinicSelect.addEventListener('change', (e) => {
					const clinicId = e.target.value;
					if (clinicId) {
						loadDoctors(clinicId);
						if (manualCalendar) manualCalendar.disabled = true;
					} else {
						if (doctorSelect) {
							doctorSelect.innerHTML = '<option value="">בחר מרפאה תחילה</option>';
							doctorSelect.disabled = true;
						}
						if (manualCalendar) manualCalendar.disabled = false;
					}
					syncGoogleStep();
				});
			}

			function syncGoogleStep() {
				const hasDoctor = doctorSelect && doctorSelect.value;
				const hasManual = manualCalendar && manualCalendar.value.trim().length > 0;

				if (doctorSelect) doctorSelect.disabled = hasManual || !clinicSelect?.value;
				if (clinicSelect) clinicSelect.disabled = hasManual;
				if (manualCalendar) manualCalendar.disabled = hasDoctor;

				if (googleNextBtn) {
					googleNextBtn.disabled = !(hasDoctor || hasManual);
				}
			}

			if (doctorSelect) {
				doctorSelect.addEventListener('change', syncGoogleStep);
			}
			if (manualCalendar) {
				['input', 'change'].forEach(evt => manualCalendar.addEventListener(evt, syncGoogleStep));
			}
			if (googleNextBtn) {
				googleNextBtn.addEventListener('click', () => {
					const detail = {
						step: 'google',
						clinic_id: clinicSelect ? clinicSelect.value : '',
						doctor_id: doctorSelect ? doctorSelect.value : '',
						manual_calendar: manualCalendar ? manualCalendar.value.trim() : '',
					};
					root.dispatchEvent(new CustomEvent('jet-multi-step:next', { detail, bubbles: true }));
				});
			}

			syncGoogleStep();

			// ==========================================
			// Step 3: Schedule Settings Logic
			// ==========================================
			const stepScheduleSettings = root.querySelector('.schedule-settings-step');
			const stepSuccess = root.querySelector('.success-step');
			const dayCheckboxes = root.querySelectorAll('.day-checkbox input[type="checkbox"]');
			const addTreatmentBtn = root.querySelector('.add-treatment-btn');
			const saveScheduleBtn = root.querySelector('.save-schedule-btn');

			// Store form data from previous steps
			let formData = {
				action_type: '', // google or clinix
				clinic_id: '',
				doctor_id: '',
				manual_calendar_name: '',
			};

			// ==========================================
			// Constants & Configuration
			// ==========================================
			
			const DAY_NAMES_HE = {
				sunday: 'יום ראשון',
				monday: 'יום שני',
				tuesday: 'יום שלישי',
				wednesday: 'יום רביעי',
				thursday: 'יום חמישי',
				friday: 'יום שישי',
				saturday: 'יום שבת'
			};

			// ==========================================
			// Helper Functions for Repeaters
			// ==========================================
			
			/**
			 * Setup remove button functionality for repeater items
			 * @param {HTMLElement} container - Parent container
			 * @param {string} itemSelector - Item selector (e.g., '.time-range-row')
			 * @param {string} btnSelector - Button selector (e.g., '.remove-time-split-btn')
			 */
			function setupRemoveButtons(container, itemSelector, btnSelector) {
				const items = container.querySelectorAll(itemSelector);
				const removeButtons = container.querySelectorAll(btnSelector);
				
				removeButtons.forEach(btn => {
					btn.addEventListener('click', function() {
						const parentContainer = this.closest(container.className ? '.' + container.className.split(' ')[0] : container.tagName);
						this.closest(itemSelector).remove();
						
						// Toggle remove button visibility based on remaining items
						const remainingItems = container.querySelectorAll(itemSelector);
						if (remainingItems.length === 1) {
							remainingItems[0].querySelector(btnSelector).style.display = 'none';
						}
					});
				});
			}

			/**
			 * Add new repeater row
			 * @param {HTMLElement} container - Parent container
			 * @param {HTMLElement} templateRow - Row to clone
			 * @param {string} itemSelector - Item selector
			 * @param {string} btnSelector - Remove button selector
			 * @param {function} clearCallback - Optional callback to clear values
			 */
			function addRepeaterRow(container, templateRow, itemSelector, btnSelector, clearCallback = null) {
				const newRow = templateRow.cloneNode(true);
				
				// Clear values if callback provided
				if (clearCallback) {
					clearCallback(newRow);
				}
				
				// Show remove buttons on all rows
				newRow.querySelector(btnSelector).style.display = 'inline-block';
				container.querySelectorAll(btnSelector).forEach(btn => {
					btn.style.display = 'inline-block';
				});
				
				container.appendChild(newRow);
				
				// Setup remove functionality for new row
				newRow.querySelector(btnSelector).addEventListener('click', function() {
					this.closest(itemSelector).remove();
					
					const remainingItems = container.querySelectorAll(itemSelector);
					if (remainingItems.length === 1) {
						remainingItems[0].querySelector(btnSelector).style.display = 'none';
					}
				});
				
				return newRow;
			}

			// Update google next button to transition to step 3
			if (googleNextBtn) {
				googleNextBtn.addEventListener('click', () => {
					const selectedAction = root.querySelector('input[name="jet_action_choice"]:checked');
					
					formData.action_type = selectedAction ? selectedAction.value : 'clinix';
					formData.clinic_id = clinicSelect ? clinicSelect.value : '';
					formData.doctor_id = doctorSelect ? doctorSelect.value : '';
					formData.manual_calendar_name = manualCalendar ? manualCalendar.value.trim() : '';

					// Move to step 3
					if (stepGoogle && stepScheduleSettings) {
						stepGoogle.classList.remove('is-active');
						stepGoogle.setAttribute('aria-hidden', 'true');
						stepScheduleSettings.classList.add('is-active');
						stepScheduleSettings.removeAttribute('aria-hidden');
						
						// Load subspecialities for treatments
						loadSubspecialities();
					}
				});
			}

			// ==========================================
			// Day Checkboxes - Show/Hide Time Ranges
			// ==========================================
			dayCheckboxes.forEach(checkbox => {
				checkbox.addEventListener('change', (e) => {
					const day = e.target.dataset.day;
					const dayTimeRange = root.querySelector(`.day-time-range[data-day="${day}"]`);
					
					if (dayTimeRange) {
						dayTimeRange.style.display = e.target.checked ? 'block' : 'none';
					}
				});
			});

			// ==========================================
			// Time Splits Management (using helper functions)
			// ==========================================
			
			// Add time split buttons
			root.querySelectorAll('.add-time-split-btn').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const day = e.target.closest('.add-time-split-btn').dataset.day;
					const timeRangesList = root.querySelector(`.time-ranges-list[data-day="${day}"]`);
					
					if (timeRangesList) {
						const firstRow = timeRangesList.querySelector('.time-range-row');
						addRepeaterRow(
							timeRangesList,
							firstRow,
							'.time-range-row',
							'.remove-time-split-btn'
						);
					}
				});
			});

			// Setup initial remove buttons for time splits
			root.querySelectorAll('.time-ranges-list').forEach(list => {
				setupRemoveButtons(list, '.time-range-row', '.remove-time-split-btn');
			});

			// ==========================================
			// Treatments Management (using helper functions)
			// ==========================================
			
			// Add treatment button
			if (addTreatmentBtn) {
				addTreatmentBtn.addEventListener('click', () => {
					const treatmentsRepeater = root.querySelector('.treatments-repeater');
					const firstTreatment = treatmentsRepeater.querySelector('.treatment-row');
					
					addRepeaterRow(
						treatmentsRepeater,
						firstTreatment,
						'.treatment-row',
						'.remove-treatment-btn',
						// Clear values callback
						(newRow) => {
							newRow.querySelectorAll('input').forEach(input => input.value = '');
							newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
						}
					);
				});
			}

			// Setup initial remove buttons for treatments
			const treatmentsRepeater = root.querySelector('.treatments-repeater');
			if (treatmentsRepeater) {
				setupRemoveButtons(treatmentsRepeater, '.treatment-row', '.remove-treatment-btn');
			}

			// Load subspecialities for treatment dropdowns
			async function loadSubspecialities() {
				const subspecialitySelects = root.querySelectorAll('.subspeciality-select');
				
				if (!formData.clinic_id) {
					console.warn('No clinic selected, cannot load subspecialities');
					return;
				}

				try {
					// Get clinic's speciality taxonomy term ID
					const clinicResponse = await fetch(`<?php echo esc_url(rest_url('wp/v2/clinics/')); ?>${formData.clinic_id}?_fields=specialities`);
					if (!clinicResponse.ok) throw new Error('Failed to load clinic data');
					
					const clinicData = await clinicResponse.json();
					const parentTermId = clinicData.specialities && clinicData.specialities.length > 0 ? clinicData.specialities[0] : null;
					
					if (!parentTermId) {
						console.warn('Clinic has no speciality assigned');
						return;
					}

					// Get child terms (subspecialities)
					const subspecialitiesResponse = await fetch(`<?php echo esc_url(rest_url('wp/v2/specialities')); ?>?parent=${parentTermId}&per_page=100`);
					if (!subspecialitiesResponse.ok) throw new Error('Failed to load subspecialities');
					
					const subspecialities = await subspecialitiesResponse.json();
					
					// Populate all subspeciality selects
					subspecialitySelects.forEach(select => {
						select.innerHTML = '<option value="">בחר תת-תחום</option>';
						subspecialities.forEach(term => {
							const option = document.createElement('option');
							option.value = term.id;
							option.textContent = term.name;
							select.appendChild(option);
						});
					});
				} catch (error) {
					console.error('Error loading subspecialities:', error);
					subspecialitySelects.forEach(select => {
						select.innerHTML = '<option value="">שגיאה בטעינת תת-תחומים</option>';
					});
				}
			}

			// Save schedule button
			if (saveScheduleBtn) {
				saveScheduleBtn.addEventListener('click', async () => {
					// Collect all schedule data
					const scheduleData = {
						...formData,
						days: {},
						treatments: []
					};

					// Collect days and time ranges
					dayCheckboxes.forEach(checkbox => {
						if (checkbox.checked) {
							const day = checkbox.dataset.day;
							const timeRangesList = root.querySelector(`.time-ranges-list[data-day="${day}"]`);
							const timeRanges = [];
							
							timeRangesList.querySelectorAll('.time-range-row').forEach(row => {
								const fromTime = row.querySelector('.from-time').value;
								const toTime = row.querySelector('.to-time').value;
								timeRanges.push({ start_time: fromTime, end_time: toTime });
							});
							
							scheduleData.days[day] = timeRanges;
						}
					});

					// Collect treatments
					root.querySelectorAll('.treatment-row').forEach(row => {
						const name = row.querySelector('input[name="treatment_name[]"]').value;
						const subspeciality = row.querySelector('select[name="treatment_subspeciality[]"]').value;
						const price = row.querySelector('input[name="treatment_price[]"]').value;
						const duration = row.querySelector('select[name="treatment_duration[]"]').value;
						
						if (name) {
							scheduleData.treatments.push({
								name: name,
								subspeciality: subspeciality,
								price: price,
								duration: duration
							});
						}
					});

					// Validate
					if (Object.keys(scheduleData.days).length === 0) {
						alert('אנא בחר לפחות יום עבודה אחד');
						return;
					}

					if (scheduleData.treatments.length === 0) {
						alert('אנא הוסף לפחות טיפול אחד');
						return;
					}

					// Show loading state
					saveScheduleBtn.disabled = true;
					saveScheduleBtn.textContent = 'שומר...';

					try {
						// Send to server
						const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'save_clinic_schedule',
								nonce: '<?php echo wp_create_nonce('save_clinic_schedule'); ?>',
								schedule_data: JSON.stringify(scheduleData)
							})
						});

						const result = await response.json();

						if (result.success) {
							// Show success screen
							showSuccessScreen(scheduleData);
						} else {
							throw new Error(result.data || 'Failed to save schedule');
						}
					} catch (error) {
						console.error('Error saving schedule:', error);
						alert('שגיאה בשמירת היומן: ' + error.message);
						saveScheduleBtn.disabled = false;
						saveScheduleBtn.textContent = 'שמירת הגדרות יומן';
					}
				});
			}

			// Show success screen
			function showSuccessScreen(scheduleData) {
				if (stepScheduleSettings && stepSuccess) {
					stepScheduleSettings.classList.remove('is-active');
					stepScheduleSettings.setAttribute('aria-hidden', 'true');
					stepSuccess.classList.add('is-active');
					stepSuccess.removeAttribute('aria-hidden');
					stepSuccess.style.display = 'block';

					// Populate schedule summary
					const daysList = stepSuccess.querySelector('.schedule-days-list');

					let summaryHTML = '';
					for (const [day, ranges] of Object.entries(scheduleData.days)) {
						const timeRanges = ranges.map(r => `${r.start_time}–${r.end_time}`).join(', ');
						summaryHTML += `<div>${DAY_NAMES_HE[day]}: ${timeRanges}</div>`;
					}
					daysList.innerHTML = summaryHTML;
				}
			}

			// Success screen actions
			const syncGoogleBtn = root.querySelector('.sync-google-btn');
			if (syncGoogleBtn) {
				syncGoogleBtn.addEventListener('click', () => {
					// TODO: Implement Google Calendar sync
					alert('תכונת סנכרון Google Calendar תתווסף בקרוב');
				});
			}
		})();
	</script>
	<?php
	return ob_get_clean();
});

