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
				// Note: JetEngine custom fields (meta) are not included in _fields parameter
				// We need to fetch without _fields or fetch meta separately
				// Using _embed to get featured_media URL
				const doctorsUrl = `${this.config.doctorsEndpoint}?include=${doctorIds.join(',')}&per_page=100&_embed`;
				
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
	 * Load all specialities with hierarchical structure
	 * Returns parent specialities (disabled) with their child subspecialities (selectable)
	 */
	async loadAllSpecialities() {
		// Check cache first
		if (this.cache.allSpecialities) {
			return this.cache.allSpecialities;
		}

		try {
			// Get all specialities (both parents and children)
			const specialitiesUrl = `${this.config.specialitiesEndpoint}?per_page=100`;
			const response = await fetch(specialitiesUrl);

			if (!response.ok) {
				throw new Error('Failed to load specialities');
			}

			const allSpecialities = await response.json();
			
			// Organize into hierarchical structure
			// First, separate parents and children
			const parents = allSpecialities.filter(term => term.parent === 0);
			const children = allSpecialities.filter(term => term.parent !== 0);
			
			// Create hierarchical array with parents as disabled options
			const hierarchicalSpecialities = [];
			
			parents.forEach(parent => {
				// Add parent as disabled option (header)
				hierarchicalSpecialities.push({
					id: `parent_${parent.id}`,
					name: parent.name,
					disabled: true,
					isParent: true
				});
				
				// Add children under this parent
				const parentChildren = children.filter(child => child.parent === parent.id);
				parentChildren.forEach(child => {
					hierarchicalSpecialities.push({
						id: child.id,
						name: `   ${child.name}`, // Add indent for visual hierarchy
						disabled: false,
						isParent: false,
						parentId: parent.id
					});
				});
			});
			
			// Cache the result
			this.cache.allSpecialities = hierarchicalSpecialities;
			
			return hierarchicalSpecialities;
		} catch (error) {
			console.error('Error loading specialities:', error);
			throw error;
		}
	}

	/**
	 * Load treatments for a specific clinic
	 * @param {number} clinicId - Clinic post ID
	 * @returns {Promise<Object>} Object with treatments data organized by category
	 */
	async loadClinicTreatments(clinicId) {
		console.log('');
		console.log('╔════════════════════════════════════════════════════════╗');
		console.log('║  🔍 טוען טיפולים למרפאה                              ║');
		console.log('╚════════════════════════════════════════════════════════╝');
		console.log('📋 Clinic ID:', clinicId);
		console.log('📋 Config endpoints:', {
			clinicsEndpoint: this.config.clinicsEndpoint,
			clinicsListEndpoint: this.config.clinicsListEndpoint
		});
		
		if (!clinicId) {
			console.error('❌ שגיאה: Clinic ID חסר!');
			throw new Error('Clinic ID is required');
		}

		try {
			// Fetch clinic data with treatments field
			const clinicUrl = `${this.config.clinicsEndpoint}/${clinicId}`;
			
			console.log('🌐 URL מלא לבקשה:', clinicUrl);
			console.log('🔗 פירוט URL:', {
				'Base Endpoint': this.config.clinicsEndpoint,
				'Clinic ID': clinicId,
				'Full URL': clinicUrl
			});
			
			console.log('📤 שולח בקשה ל-API...');
			
			const response = await fetch(clinicUrl, {
				headers: {
					'X-WP-Nonce': this.config.restNonce || ''
				}
			});

			console.log('📥 התקבלה תשובה מה-API');
			console.log('📊 Status:', response.status, response.statusText);
			console.log('📊 Response URL:', response.url);

			if (!response.ok) {
				console.error('❌ שגיאה: הבקשה נכשלה!');
				console.error('📊 Status Code:', response.status);
				console.error('📊 Status Text:', response.statusText);
				throw new Error(`Failed to load clinic: ${response.status}`);
			}

			const clinic = await response.json();
			console.log('✅ נתוני מרפאה התקבלו בהצלחה');
			console.log('📦 Clinic Object:', clinic);
			console.log('📦 Clinic ID:', clinic.id);
			console.log('📦 Clinic Title:', clinic.title?.rendered || clinic.title);
			
			// Get treatments from REST API (exposed via register_rest_field)
			let treatments = [];
			if (clinic.treatments && Array.isArray(clinic.treatments)) {
				treatments = clinic.treatments;
				console.log('✅ נמצאו טיפולים במרפאה:', treatments.length);
			} else {
				console.warn('⚠️  לא נמצאו טיפולים במרפאה זו');
				console.log('📦 clinic.treatments:', clinic.treatments);
			}
			
			// Show each treatment
			if (treatments.length > 0) {
				console.log('');
				console.log('📋 רשימת טיפולים:');
				console.log('┌─────────────────────────────────────────────────────┐');
				treatments.forEach((treatment, index) => {
					console.log(`│ ${index + 1}. ${treatment.treatment_type || 'ללא שם'}`);
					console.log(`│    └─ תת-תחום ID: ${treatment.sub_speciality || 'ללא'}`);
					console.log(`│    └─ מחיר: ${treatment.cost || 0} ₪`);
					console.log(`│    └─ משך: ${treatment.duration || 0} דקות`);
				});
				console.log('└─────────────────────────────────────────────────────┘');
			}

			// Cache the treatments
			this.cache.clinicTreatments = treatments;
			
			// Organize treatments by sub_speciality
			const treatmentsByCategory = {};
			const categories = new Set();
			
			treatments.forEach(treatment => {
				const subSpeciality = treatment.sub_speciality || 0;
				categories.add(subSpeciality);
				
				if (!treatmentsByCategory[subSpeciality]) {
					treatmentsByCategory[subSpeciality] = [];
				}
				
				treatmentsByCategory[subSpeciality].push(treatment);
			});
			
			console.log('');
			console.log('📊 ארגון לפי תת-תחומים:');
			console.log('┌─────────────────────────────────────────────────────┐');
			for (const [categoryId, categoryTreatments] of Object.entries(treatmentsByCategory)) {
				console.log(`│ תת-תחום ${categoryId}: ${categoryTreatments.length} טיפולים`);
			}
			console.log('└─────────────────────────────────────────────────────┘');
			console.log('📊 סך הכל תת-תחומים:', categories.size);
			console.log('📊 רשימת תת-תחומים:', Array.from(categories));
			console.log('');
			console.log('✅ סיום טעינת טיפולים בהצלחה!');
			console.log('════════════════════════════════════════════════════════');
			console.log('');

			return {
				treatments,
				treatmentsByCategory,
				categories: Array.from(categories)
			};
		} catch (error) {
			console.error('');
			console.error('╔════════════════════════════════════════════════════════╗');
			console.error('║  ❌ שגיאה בטעינת טיפולים!                            ║');
			console.error('╚════════════════════════════════════════════════════════╝');
			console.error('Error:', error);
			console.error('Error Message:', error.message);
			console.error('Error Stack:', error.stack);
			console.error('════════════════════════════════════════════════════════');
			console.error('');
			throw error;
		}
	}

	/**
	 * Get category name by term ID
	 * @param {number} termId - Term ID from glossary taxonomy
	 * @returns {Promise<string>} Category name
	 */
	async getCategoryName(termId) {
		if (!termId || termId === 0) {
			return 'ללא תת-תחום';
		}
		
		try {
			// Fetch term directly from API for accurate name
			const termUrl = `${this.config.specialitiesEndpoint}/${termId}`;
			const response = await fetch(termUrl);
			
			if (!response.ok) {
				// Fallback: try to find in cached specialities
				if (!this.cache.allSpecialities) {
					await this.loadAllSpecialities();
				}
				
				const speciality = this.cache.allSpecialities.find(s => s.id === termId && !s.isParent);
				return speciality ? speciality.name.trim() : `תת-תחום #${termId}`;
			}
			
			const term = await response.json();
			return term.name || `תת-תחום #${termId}`;
		} catch (error) {
			console.error('Error getting category name:', error);
			return `תת-תחום #${termId}`;
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

