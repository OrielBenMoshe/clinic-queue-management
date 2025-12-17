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
				const endpoint = this.config.clinicsEndpoint || '';
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
				console.error('Error loading clinics:', error);
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
				// Use JetEngine relation API
				const relationId = this.config.relationId || 5;
				const jetRelEndpoint = this.config.jetRelEndpoint || '';
				
				if (!jetRelEndpoint) {
					throw new Error('JetRel endpoint not configured');
				}

				const url = `${jetRelEndpoint}${relationId}/children/${clinicId}`;
				const response = await fetch(url);

				if (!response.ok) {
					throw new Error(`Failed to load doctors from relation ${relationId}: ${response.status}`);
				}

				const relationData = await response.json();

				// Extract doctor IDs
				const doctorIds = [];
				if (Array.isArray(relationData) && relationData.length > 0) {
					relationData.forEach(item => {
						if (item.child_object_id) {
							doctorIds.push(item.child_object_id);
						}
					});
				}

				if (doctorIds.length === 0) {
					return [];
				}

				// Fetch doctor details from REST API
				const doctorsUrl = `${this.config.doctorsEndpoint}?include=${doctorIds.join(',')}&per_page=100&_fields=id,title`;
				
				const doctorsResponse = await fetch(doctorsUrl, {
					headers: {
						'X-WP-Nonce': this.config.restNonce || ''
					}
				});

				if (doctorsResponse.ok) {
					const doctors = await doctorsResponse.json();
					
					if (doctors && doctors.length > 0) {
						return doctors;
					} else {
						// Fallback: load doctors individually
						return await this.loadDoctorsIndividually(doctorIds);
					}
				} else {
					// Fallback: load doctors individually
					return await this.loadDoctorsIndividually(doctorIds);
				}
			} catch (error) {
				console.error('Error loading doctors:', error);
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
					const doctorUrl = `${this.config.doctorsEndpoint}${doctorId}?_fields=id,title`;
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
		 * Load subspecialities for a specific clinic
		 */
		async loadSubspecialities(clinicId) {
			if (!clinicId) {
				throw new Error('Clinic ID is required');
			}

			try {
				// First, get clinic's speciality
				const clinicUrl = `${this.config.clinicsEndpoint.replace('?per_page=30&author=', '/')}${clinicId}?_fields=specialities`;
				const clinicResponse = await fetch(clinicUrl);

				if (!clinicResponse.ok) {
					throw new Error('Failed to load clinic data');
				}

				const clinicData = await clinicResponse.json();
				const parentTermId = clinicData.specialities && clinicData.specialities.length > 0 
					? clinicData.specialities[0] 
					: null;

				if (!parentTermId) {
					return [];
				}

				// Get child terms (subspecialities)
				const subspecialitiesUrl = `${this.config.specialitiesEndpoint}?parent=${parentTermId}&per_page=100`;
				const subspecialitiesResponse = await fetch(subspecialitiesUrl);

				if (!subspecialitiesResponse.ok) {
					throw new Error('Failed to load subspecialities');
				}

				const subspecialities = await subspecialitiesResponse.json();
				
				// Cache the result
				this.cache[`subspecialities_${clinicId}`] = subspecialities;
				
				return subspecialities;
			} catch (error) {
				console.error('Error loading subspecialities:', error);
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
				console.error('Error saving schedule:', error);
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
	}

	// Export to global scope
	window.ScheduleFormDataManager = ScheduleFormDataManager;

})(window);

