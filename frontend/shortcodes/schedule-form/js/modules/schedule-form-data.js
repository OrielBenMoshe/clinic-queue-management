/**
 * Schedule Form Data Module
 * Handles all API calls and data fetching
 * 
 * @package Clinic_Queue_Management
 */

(function(window) {
	'use strict';

	/**
	 * Schedule Form Data Manager
	 */
	class ScheduleFormDataManager {
		constructor(config) {
			this.config = config || {};
			this.cache = {};
		}

	/**
	 * Load clinics for current user
	 */
	async loadClinics() {
		try {
			// Use clinicsListEndpoint for listing (with filters), clinicsEndpoint for single clinic
			const endpoint = this.config.clinicsListEndpoint || this.config.clinicsEndpoint || '';
			if (!endpoint) {
				throw new Error('Clinics endpoint not configured');
			}

			const response = await fetch(endpoint, {
				headers: {
					'X-WP-Nonce': this.config.restNonce || ''
				}
			});

			if (!response.ok) {
				throw new Error(`Failed to load clinics: ${response.status} ${response.statusText}`);
			}

			const clinics = await response.json();
			
			// Cache the result
			this.cache.clinics = clinics;
			
			return clinics;
		} catch (error) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error('Error loading clinics', error);
			} else {
				console.error('Error loading clinics:', error);
			}
			throw error;
		}
	}

		/**
		 * Load doctors for a specific clinic using JetEngine relations
		 */
		async loadDoctors(clinicId) {
			if (!clinicId) {
				throw new Error('Clinic ID is required');
			}

			try {
				// Use custom REST API endpoint that internally calls Jet Relations API
				// Format: GET /wp-json/clinic-queue/v1/relations/clinic/{clinicId}/doctors
				const restUrl = this.config.restUrl || '';
				if (!restUrl) {
					throw new Error('REST API URL not configured');
				}

				// Build URL for custom relations endpoint
				const baseEndpoint = restUrl.replace(/\/$/, ''); // Remove trailing slash if exists
				const url = `${baseEndpoint}/relations/clinic/${clinicId}/doctors`;
				
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log(`Loading doctors for clinic ${clinicId} from API endpoint: ${url}`);
				}
				
			const response = await fetch(url, {
				headers: {
					'X-WP-Nonce': this.config.restNonce || ''
				}
			});

			if (!response.ok) {
				const errorText = await response.text();
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error(`Failed to load doctors for clinic ${clinicId}: ${response.status}`, { url, errorText });
				}
				throw new Error(`Failed to load doctors for clinic ${clinicId}: ${response.status}`);
			}

			// Parse JSON response
			const doctors = await response.json();

			// The endpoint returns full doctor details directly
			if (!Array.isArray(doctors)) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.warn(`Invalid response format for doctors`, { doctors });
					}
					return [];
				}

				if (doctors.length === 0) {
					if (window.ScheduleFormUtils) {
						window.ScheduleFormUtils.warn(`No doctors found for clinic ${clinicId}`);
					}
					return [];
				}

				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.log(`Successfully loaded ${doctors.length} doctors for clinic ${clinicId}`);
				}

				return doctors;
			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error loading doctors', error);
				} else {
					console.error('Error loading doctors:', error);
				}
				// Log the full error details for debugging
				console.error('loadDoctors error details:', {
					clinicId,
					restUrl: this.config.restUrl,
					error: error.message,
					stack: error.stack
				});
				throw error;
			}
		}

		/**
		 * Load doctors one by one (fallback)
		 */
		async loadDoctorsIndividually(doctorIds) {
			const loadedDoctors = [];

			for (const doctorId of doctorIds) {
				try {
					// Fetch full doctor data to get meta fields (JetEngine custom fields)
					const doctorUrl = `${this.config.doctorsEndpoint}${doctorId}?&_embed`;
					const response = await fetch(doctorUrl, {
						headers: {
							'X-WP-Nonce': this.config.restNonce || ''
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

			return loadedDoctors;
		}

	/**
	 * Load treatments for a specific clinic
	 * @param {number} clinicId - Clinic post ID
	 * @returns {Promise<Object>} Object with treatments data organized by category
	 */
	async loadClinicTreatments(clinicId) {
		if (!clinicId) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error('Clinic ID is required');
			} else {
				console.error('Clinic ID is required');
			}
			throw new Error('Clinic ID is required');
		}

		try {
			// Fetch clinic data with treatments field
			const clinicUrl = `${this.config.clinicsEndpoint}/${clinicId}`;
			
			const response = await fetch(clinicUrl, {
				headers: {
					'X-WP-Nonce': this.config.restNonce || ''
				}
			});

			if (!response.ok) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error(`Failed to load clinic: ${response.status} ${response.statusText}`);
				} else {
					console.error(`Failed to load clinic: ${response.status}`);
				}
				throw new Error(`Failed to load clinic: ${response.status}`);
			}

			const clinic = await response.json();
			
			// Get treatments from REST API (exposed via register_rest_field)
			let treatments = [];
			if (clinic.treatments && Array.isArray(clinic.treatments)) {
				treatments = clinic.treatments;
			} else {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.warn('No treatments found for clinic', { clinicId, treatments: clinic.treatments });
				} else {
					console.warn('No treatments found for clinic');
				}
			}

			// Cache the treatments
			this.cache.clinicTreatments = treatments;
			
			// Log success (using utils if available)
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.log(`Loaded ${treatments.length} treatments for clinic ${clinicId}`);
			}

			return {
				treatments
			};
		} catch (error) {
			if (window.ScheduleFormUtils) {
				window.ScheduleFormUtils.error('Error loading clinic treatments', error);
			} else {
				console.error('Error loading clinic treatments:', error);
			}
			throw error;
		}
	}

		/**
		 * Save schedule data
		 */
		async saveSchedule(scheduleData) {
			try {
				const response = await fetch(this.config.ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'save_clinic_schedule',
						nonce: this.config.saveNonce,
						schedule_data: JSON.stringify(scheduleData)
					})
				});

				const result = await response.json();

				if (!result.success) {
					throw new Error(result.data || 'Failed to save schedule');
				}

				return result;
			} catch (error) {
				if (window.ScheduleFormUtils) {
					window.ScheduleFormUtils.error('Error saving schedule', error);
				} else {
					console.error('Error saving schedule:', error);
				}
				throw error;
			}
		}

		/**
		 * Get formatted doctor name
		 */
		getDoctorName(doctor) {
			if (doctor.title && doctor.title.rendered) {
				return doctor.title.rendered;
			} else if (doctor.title && typeof doctor.title === 'string') {
				return doctor.title;
			} else if (doctor.name) {
				return doctor.name;
			} else if (doctor.post_title) {
				return doctor.post_title;
			} else {
				return `רופא #${doctor.id}`;
			}
		}

		/**
		 * Get doctor license number
		 * JetEngine custom fields are now exposed via register_rest_field
		 */
		getDoctorLicenseNumber(doctor) {
			// Check direct property (exposed via register_rest_field)
			if (doctor.license_number) {
				return doctor.license_number;
			}
			
			// Fallback: Check meta object (if REST API exposes meta directly)
			if (doctor.meta && doctor.meta.license_number) {
				// Meta values can be arrays, so handle both cases
				const value = doctor.meta.license_number;
				return Array.isArray(value) ? value[0] : value;
			}
			
			// Fallback: Check acf (if using ACF)
			if (doctor.acf && doctor.acf.license_number) {
				return doctor.acf.license_number;
			}
			
			return '';
		}

		/**
		 * Get doctor thumbnail URL
		 * JetEngine custom fields are now exposed via register_rest_field
		 */
		getDoctorThumbnail(doctor) {
			// Check direct property (exposed via register_rest_field)
			if (doctor.thumbnail) {
				if (typeof doctor.thumbnail === 'string') {
					return doctor.thumbnail;
				} else if (doctor.thumbnail.url) {
					return doctor.thumbnail.url;
				} else if (doctor.thumbnail.full) {
					return doctor.thumbnail.full;
				}
			}
			
			// Fallback: Check _embedded for featured media
			if (doctor._embedded && doctor._embedded['wp:featuredmedia'] && doctor._embedded['wp:featuredmedia'][0]) {
				const featuredMedia = doctor._embedded['wp:featuredmedia'][0];
				if (featuredMedia.source_url) {
					return featuredMedia.source_url;
				} else if (featuredMedia.media_details && featuredMedia.media_details.sizes) {
					// Try to get thumbnail or medium size
					if (featuredMedia.media_details.sizes.thumbnail) {
						return featuredMedia.media_details.sizes.thumbnail.source_url;
					} else if (featuredMedia.media_details.sizes.medium) {
						return featuredMedia.media_details.sizes.medium.source_url;
					} else if (featuredMedia.media_details.sizes.full) {
						return featuredMedia.media_details.sizes.full.source_url;
					}
				}
			}
			
			// Fallback: Check meta fields (if REST API exposes meta directly)
			if (doctor.meta && doctor.meta.thumbnail) {
				const thumbnail = doctor.meta.thumbnail;
				// Meta values can be arrays or objects
				if (Array.isArray(thumbnail)) {
					return thumbnail[0] || '';
				} else if (typeof thumbnail === 'string') {
					return thumbnail;
				} else if (thumbnail.url) {
					return thumbnail.url;
				}
			}
			
			// Fallback: Check ACF fields (if using ACF)
			if (doctor.acf && doctor.acf.thumbnail) {
				if (typeof doctor.acf.thumbnail === 'string') {
					return doctor.acf.thumbnail;
				} else if (doctor.acf.thumbnail.url) {
					return doctor.acf.thumbnail.url;
				}
			}
			
			return '';
		}
	}

	// Export to global scope
	window.ScheduleFormDataManager = ScheduleFormDataManager;

})(window);

