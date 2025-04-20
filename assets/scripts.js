// global
const CONFIG = {
  selectors: {
    forms: {
      edit: '.edit-form',
      add: '.add-form'
    }
  }
};


// core
const Utils = {
  toggleForm(formName) {
    const form = document.getElementById(formName);
    const overlay = document.getElementById('form-overlay');

    if (!form) {
      console.error(`Form not found for ${formName}`);
      return;
    } else if (!overlay) {
      console.error(`Overlay not found for ${overlay}`);
      return;
    }

    const isCurrentlyHidden = form.style.display === 'none' || form.style.display === '';

    if (isCurrentlyHidden) {
      overlay.style.display = 'block';
      form.style.display = 'block';

      setTimeout(() => {
        overlay.classList.add('active');
        form.classList.add('active');
      }, 10);
    } else {
      overlay.classList.remove('active');
      form.classList.remove('active');

      setTimeout(() => {
        overlay.style.display = 'none';
        form.style.display = 'none';
      }, 300);
    }
  },

  hideAllElements(selector) {
    const elements = document.querySelectorAll(selector);
    elements.forEach(element => {
      element.style.display = 'none';
    });
  },

  formatCurrency(amount) {
    return `£${parseFloat(amount).toFixed(2)}`;
  },

  playNotificationSound() {
    const sound = new Audio('/LuckyNest/assets/sounds/notification.mp3');
    sound.play().catch(error => {
      console.error('Failed to play notification sound:', error);
    });
  }
};

// User Management Module
const UserManagementModule = {
  init() {
  },

  confirmRoleChange(userId, currentRole) {
    let newRole = currentRole === 'guest' ? 'admin' : 'guest';
    if (confirm(`Are you sure you want to change this user's role from ${currentRole} to ${newRole}?`)) {
      document.getElementById(`role_${userId}_confirmed`).value = 'yes';
      document.getElementById(`role_${userId}_form`).submit();
    }
  }
};

// Notification Module
const NotificationModule = {
  init() {
    this.setupNotificationListener();
  },

  setupNotificationListener() {
    const eventSource = new EventSource('/LuckyNest/include/notification_listener.php');

    eventSource.addEventListener('notification', (event) => {
      const data = JSON.parse(event.data);
      this.showNotification(data.message);
      Utils.playNotificationSound();
    });

    eventSource.onerror = (error) => {
      console.error('EventSource error:', error);
      eventSource.close();

      setTimeout(() => this.setupNotificationListener(), 5000);
    };
  },

  showNotification(message) {
    console.log('New notification:', message);

    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.innerHTML = message;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('show');
    }, 10);

    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => {
        document.body.removeChild(toast);
      }, 300);
    }, 5000);
  }
};

// Meals
const MealModule = {
  init() {
    const mealPlanSelector = document.getElementById('meal-plan-selector');
    if (mealPlanSelector) {
      mealPlanSelector.addEventListener('change', () => {
        this.showMealPlanDetails(mealPlanSelector.value);
      });
    }

    const mealCheckboxes = document.querySelectorAll('input[name="meals[]"]');
    if (mealCheckboxes.length > 0) {
      mealCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', this.calculateTotal);
      });
      this.calculateTotal();
    }

    const mealPlanCheckboxes = document.querySelectorAll('input[name="meal_plan_ids[]"]');
    if (mealPlanCheckboxes.length > 0) {
      mealPlanCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', this.updateSelectedPlansCount);
      });
    }

    this.initMealModal();
    this.initMealPlanDatePickers();
    this.initMealAssignmentModule();
  },

  showMealPlanDetails(planId) {
    const planDetails = document.querySelectorAll('.meal-plan-details');
    planDetails.forEach(plan => {
      plan.style.display = 'none';
    });

    if (planId) {
      const selectedPlan = document.getElementById(`meal-plan-${planId}`);
      if (selectedPlan) {
        selectedPlan.style.display = 'block';
      }
    }
  },

  calculateTotal() {
    const selectedMeals = document.querySelectorAll('input[name="meals[]"]:checked');
    let total = 0;

    selectedMeals.forEach(meal => {
      total += parseFloat(meal.getAttribute('data-price'));
    });

    document.getElementById('total-price').textContent = Utils.formatCurrency(total);
  },

  updateSelectedPlansCount() {
    const selectedPlans = document.querySelectorAll('input[name="meal_plan_ids[]"]:checked');
    const submitButton = document.querySelector('button[type="submit"]');

    if (submitButton) {
      if (selectedPlans.length > 0) {
        submitButton.textContent = `Book ${selectedPlans.length} Selected Plan${selectedPlans.length > 1 ? 's' : ''}`;
      } else {
        submitButton.textContent = 'Book Selected Plans';
      }
    }
  },

  initMealModal() {
    const modal = document.getElementById('mealModal');
    if (!modal) return;

    const closeBtn = modal.querySelector('.close');
    const mealLinks = document.querySelectorAll('.meal-name-link');

    if (closeBtn) {
      closeBtn.onclick = function () {
        modal.style.display = "none";
      };
    }

    window.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.style.display = "none";
      }
    });

    mealLinks.forEach(link => {
      link.addEventListener('click', function () {
        const mealId = this.getAttribute('data-meal-id');
        const mealName = this.getAttribute('data-meal-name');
        const mealType = this.getAttribute('data-meal-type');
        const mealPrice = this.getAttribute('data-meal-price');
        const mealTags = this.getAttribute('data-meal-tags');
        const mealImage = this.getAttribute('data-meal-image');

        document.getElementById('modalMealName').textContent = mealName;
        document.getElementById('modalMealType').textContent = mealType;
        document.getElementById('modalMealPrice').textContent = mealPrice;
        document.getElementById('modalMealTags').textContent = mealTags;

        const imgElement = document.getElementById('modalMealImage');
        if (mealImage && mealImage !== '') {
          imgElement.src = mealImage;
          document.getElementById('modalImageContainer').style.display = 'block';
        } else {
          document.getElementById('modalImageContainer').style.display = 'none';
        }

        modal.style.display = "block";
      });
    });
  },

  initMealPlanDatePickers() {
    if (!window.flatpickr) return;

    const datePickers = document.querySelectorAll('.meal-plan-date-picker');

    datePickers.forEach(picker => {
      const planType = picker.getAttribute('data-plan-type');

      let config = {
        minDate: "today",
        dateFormat: "Y-m-d",
      };

      if (planType === 'Daily') {
        config.onDayCreate = function (dObj, dStr, fp, dayElem) {
          dayElem.style.backgroundColor = "#e6ffe6";
        };
      }
      else if (planType === 'Weekly') {
        config.onDayCreate = function (dObj, dStr, fp, dayElem) {
          const dayOfWeek = dayElem.dateObj.getDay();
          if (dayOfWeek === 1) { // Monday is 1
            dayElem.style.backgroundColor = "#e6ffe6";
          } else {
            dayElem.classList.add("flatpickr-disabled");
          }
        };
        config.enable = [
          function (date) {
            return date.getDay() === 1;
          }
        ];
      }
      else if (planType === 'Monthly') {
        config = {
          minDate: "today",
          dateFormat: "Y-m-d",
          onDayCreate: function (dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj.getDate() === 1) { // 1st day of month
              dayElem.style.backgroundColor = "#e6ffe6";
            } else {
              dayElem.classList.add("flatpickr-disabled");
            }
          },
          enable: [
            function (date) {
              return date.getDate() === 1;
            }
          ],
          plugins: [
            new window.flatpickrMonthSelectPlugin({
              shorthand: true,
              dateFormat: "Y-m-d",
              altFormat: "F Y"
            })
          ]
        };
      }

      flatpickr(picker, config);
    });
  },

  // Meal Assignment Module
  initMealAssignmentModule() {
    document.addEventListener('DOMContentLoaded', function () {
      const planSelect = document.getElementById('plan_id');
      if (planSelect) {
        updateDayOptions(planSelect.value);
      }
    });
  },

  updateDayOptions(planId) {
    const planSelect = document.getElementById('plan_id');
    if (!planSelect) return;

    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const planType = selectedOption.getAttribute('data-plan-type');
    const daySelect = document.getElementById('day_number');
    const dayContainer = document.getElementById('day_selection_container');
    const defaultDayInput = document.getElementById('default_day_number');

    if (planType === 'Daily') {
      // Hide day selection for daily plans and use default value
      dayContainer.style.display = 'none';
      defaultDayInput.disabled = false;
      return;
    } else {
      // Show day selection for weekly and monthly plans
      dayContainer.style.display = 'block';
      defaultDayInput.disabled = true;

      // Clear existing options
      daySelect.innerHTML = '';

      if (planType === 'Weekly') {
        const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        for (let i = 0; i < 7; i++) {
          const option = document.createElement('option');
          option.value = i + 1;
          option.textContent = daysOfWeek[i];
          daySelect.appendChild(option);
        }
      } else if (planType === 'Monthly') {
        // Get the current month's days or stored month for this plan
        fetch(`meal_assignment.php?ajax=get_month_days&plan_id=${planId}`)
          .then(response => response.json())
          .then(data => {
            const daysInMonth = data.days || 30; // Default to 30 if not specified

            for (let i = 1; i <= daysInMonth; i++) {
              const option = document.createElement('option');
              option.value = i;
              option.textContent = `Day ${i}`;
              daySelect.appendChild(option);
            }
          })
          .catch(error => {
            console.error('Error fetching month days:', error);
            // Fallback to 31 days if fetch fails
            for (let i = 1; i <= 31; i++) {
              const option = document.createElement('option');
              option.value = i;
              option.textContent = `Day ${i}`;
              daySelect.appendChild(option);
            }
          });
      }
    }
  }
};

// Laundry
const LaundryModule = {
  init() {
    const recurringCheckbox = document.getElementById('recurring');
    if (recurringCheckbox) {
      recurringCheckbox.addEventListener('change', () => {
        document.getElementById('recurring_options').style.display =
          recurringCheckbox.checked ? 'block' : 'none';
      });
    }

    const editRecurringCheckboxes = document.querySelectorAll('[id^="edit-recurring-"]');
    editRecurringCheckboxes.forEach(checkbox => {
      if (checkbox.id.indexOf('-options-') === -1) {
        const slotId = checkbox.id.split('-').pop();
        checkbox.addEventListener('change', function () {
          document.getElementById(`edit-recurring-options-${slotId}`).style.display =
            this.checked ? 'block' : 'none';
        });
      }
    });

    this.initEndDateToggle();
  },

  toggleDeleteLaundryForm(slotId) {
    const deleteOptions = document.getElementById(`delete-options-${slotId}`);
    if (deleteOptions) {
      deleteOptions.style.display = 'block';
    }
  },

  initLaundryCalendar(selectedDate, options = {}) {
    const datePicker = document.getElementById('date-picker');
    if (!datePicker || !window.flatpickr) return;

    const datesWithSlots = window.datesWithSlots || [];
    const datesWithAvailableSlots = window.datesWithAvailableSlots || [];
    const datesWithNoAvailableSlots = window.datesWithNoAvailableSlots || [];

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const config = {
      dateFormat: "Y-m-d",
      defaultDate: selectedDate,
      inline: true,
      minDate: "today",
      onChange: function (selectedDates, dateStr) {
        window.location.href = 'laundry.php?date=' + dateStr;
      },
      onDayCreate: function (dObj, dStr, fp, dayElem) {
        const year = dayElem.dateObj.getFullYear();
        const month = String(dayElem.dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dayElem.dateObj.getDate()).padStart(2, '0');
        const dateStr = `${year}-${month}-${day}`;

        if (dayElem.dateObj < today) {
          dayElem.className += " flatpickr-disabled";
        }
        else {
          if (datesWithAvailableSlots && datesWithAvailableSlots.includes(dateStr)) {
            dayElem.className += " available-slots";
          } else if (datesWithNoAvailableSlots && datesWithNoAvailableSlots.includes(dateStr)) {
            dayElem.className += " no-available-slots";
          }
        }
      }
    };

    if (options) {
      Object.keys(options).forEach(key => {
        config[key] = options[key];
      });
    }

    flatpickr(datePicker, config);

    const addButton = document.querySelector('button[onclick="LuckyNest.toggleForm(\'add-form\')"]');
    if (addButton) {
      addButton.addEventListener('click', function () {
        const dateField = document.getElementById('selected_date');
        const datePicker = document.getElementById('date-picker');
        if (dateField && datePicker) {
          dateField.value = datePicker.value;
        }
      });
    }
  },

  initEndDateToggle() {
    document.addEventListener('DOMContentLoaded', function () {
      const recurringCheckbox = document.getElementById('recurring');
      if (recurringCheckbox) {
        recurringCheckbox.addEventListener('change', this.toggleEndDateField);
      }
    }.bind(this));
  },

  toggleEndDateField() {
    const recurringCheckbox = document.getElementById('recurring');
    const endDateContainer = document.getElementById('end_date_container');

    if (recurringCheckbox && endDateContainer) {
      if (recurringCheckbox.checked) {
        endDateContainer.style.display = 'block';
        document.getElementById('end_date').required = true;
      } else {
        endDateContainer.style.display = 'none';
        document.getElementById('end_date').required = false;
      }
    }
  },

  getGlobalArray(arrayName) {
    try {
      return window[arrayName] || [];
    } catch (e) {
      console.error(`Failed to get global array ${arrayName}:`, e);
      return [];
    }
  }
};

// Booking
const BookingModule = {
  init() {
    try {
      const bookedDatesElement = document.getElementById("booked-dates");
      if (!bookedDatesElement) return;

      const bookedDates = JSON.parse(bookedDatesElement.textContent);
      const allDateInputs = document.querySelectorAll('input[type="date"]');

      allDateInputs.forEach(dateInput => {
        if (window.flatpickr) {
          this.initDatePicker(dateInput, bookedDates);
        }
      });
    } catch (err) {
      console.error('Error initializing booking calendar:', err);
    }
  },

  initDatePicker(dateInput, bookedDates) {
    const form = dateInput.closest('form');
    if (!form) return;

    const roomIdInput = form.querySelector('select[name="room_id"], input[name="room_id"]');
    if (!roomIdInput) {
      if (window.flatpickr) {
        flatpickr(dateInput, {
          minDate: "today",
          dateFormat: "Y-m-d"
        });
      }
      return;
    }

    const roomId = roomIdInput.value;

    const guestIdInput = form.querySelector('select[name="guest_id"], input[name="guest_id"]');
    if (!guestIdInput) return;

    const guestId = guestIdInput.value;

    const bookingIdInput = form.querySelector('input[name="booking_id"]');
    const bookingId = bookingIdInput ? bookingIdInput.value : null;

    flatpickr(dateInput, {
      minDate: "today",
      dateFormat: "Y-m-d",
      onDayCreate: (dObj, dStr, fp, dayElem) => {
        const currentDate = dayElem.dateObj;
        const isRoomBooked = this.isDateBooked(currentDate, bookedDates, roomId, null, bookingId);
        const isGuestBooked = this.isGuestBooked(currentDate, bookedDates, guestId, bookingId);

        if (isRoomBooked || isGuestBooked) {
          dayElem.classList.add("booked-date");
          dayElem.style.backgroundColor = "#ffcccc";
          dayElem.style.color = "#666";
        }
      }
    });

    if (roomIdInput.tagName === 'SELECT') {
      roomIdInput.addEventListener('change', () => {
        if (dateInput._flatpickr) {
          dateInput._flatpickr.destroy();
        }
        this.initDatePicker(dateInput, bookedDates);
      });
    }

    if (guestIdInput.tagName === 'SELECT') {
      guestIdInput.addEventListener('change', () => {
        if (dateInput._flatpickr) {
          dateInput._flatpickr.destroy();
        }
        this.initDatePicker(dateInput, bookedDates);
      });
    }
  },

  isDateBooked(currentDate, bookedDates, roomId, guestId, currentBookingId) {
    if (!currentDate || !bookedDates || !roomId) return false;

    currentDate.setHours(0, 0, 0, 0);

    return bookedDates.some(booking => {
      if (currentBookingId && booking.booking_id == currentBookingId) {
        return false;
      }

      if (booking.room_id != roomId) {
        return false;
      }

      const startDate = new Date(booking.start);
      const endDate = new Date(booking.end);

      startDate.setHours(0, 0, 0, 0);
      endDate.setHours(0, 0, 0, 0);

      return currentDate >= startDate && currentDate <= endDate;
    });
  },

  isGuestBooked(currentDate, bookedDates, guestId, currentBookingId) {
    if (!currentDate || !bookedDates || !guestId) return false;

    currentDate.setHours(0, 0, 0, 0);

    return bookedDates.some(booking => {
      if (currentBookingId && booking.booking_id == currentBookingId) {
        return false;
      }

      if (booking.guest_id != guestId) {
        return false;
      }

      const startDate = new Date(booking.start);
      const endDate = new Date(booking.end);

      startDate.setHours(0, 0, 0, 0);
      endDate.setHours(0, 0, 0, 0);

      return currentDate >= startDate && currentDate <= endDate;
    });
  }
};

// Payment
const PaymentModule = {
  roomRates: {},
  mealPlanPrices: {},
  laundryPrices: {},

  init(config) {
    if (config) {
      this.roomRates = config.roomRates || {};
      this.mealPlanPrices = config.mealPlanPrices || {};
      this.laundryPrices = config.laundryPrices || {};
    }

    const bookingSelect = document.getElementById('booking_selection');
    if (bookingSelect && bookingSelect.options.length > 1) {
      bookingSelect.selectedIndex = 1;
      this.updateBookingDetails();
    }

    const mealPlanSelect = document.getElementById('meal_plan_selection');
    if (mealPlanSelect) {
      mealPlanSelect.addEventListener('change', () => this.calculateAmount());
    }

    const laundrySelect = document.getElementById('laundry_selection');
    if (laundrySelect) {
      laundrySelect.addEventListener('change', () => this.calculateAmount());
    }
  },

  updateBookingDetails() {
    const select = document.getElementById('booking_selection');
    if (!select) return;

    if (select.value === "") {
      ['amount', 'check_in_date', 'check_out_date', 'room_id'].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.value = "";
      });
      return;
    }

    const option = select.options[select.selectedIndex];

    const roomIdEl = document.getElementById('room_id');
    const checkInEl = document.getElementById('check_in_date');
    const checkOutEl = document.getElementById('check_out_date');

    if (roomIdEl) roomIdEl.value = option.dataset.roomId;
    if (checkInEl) checkInEl.value = option.dataset.checkIn;
    if (checkOutEl) checkOutEl.value = option.dataset.checkOut;

    this.calculateAmount();
  },

  showPaymentForm(type) {
    ['rent_form', 'meal_plan_form', 'laundry_form', 'deposit_form'].forEach(formId => {
      const formElement = document.getElementById(formId);
      if (formElement) {
        formElement.style.display = 'none';
        formElement.classList.remove('active');
      }
    });

    const overlay = document.getElementById('form-overlay');

    const selectedForm = document.getElementById(`${type}_form`);
    if (selectedForm) {
      selectedForm.style.display = 'block';

      if (overlay) {
        overlay.style.display = 'block';
        setTimeout(() => {
          overlay.classList.add('active');
        }, 10);
      }

      setTimeout(() => {
        selectedForm.classList.add('active');
      }, 10);
    }

    const paymentTypeInputs = document.querySelectorAll('input[id="payment_type_hidden"]');
    paymentTypeInputs.forEach(input => {
      input.value = type;
    });

    setTimeout(() => this.calculateAmount(), 50);
  },

  calculateAmount() {
    const visibleForm = ['rent_form', 'meal_plan_form', 'laundry_form', 'deposit_form'].find(id => {
      const form = document.getElementById(id);
      return form && form.style.display !== 'none';
    });

    if (!visibleForm) return;

    const formType = visibleForm.replace('_form', '');

    const amountField = document.querySelector(`#${visibleForm} input[name="amount"]`);
    if (!amountField) return;

    let amount = 0;

    switch (formType) {
      case 'rent':
        const roomId = document.getElementById('room_id')?.value;
        if (roomId && this.roomRates[roomId]) {
          amount = parseFloat(this.roomRates[roomId]);
        }
        break;

      case 'meal_plan':
        const mealPlanSelect = document.getElementById('meal_plan_selection');
        if (mealPlanSelect && mealPlanSelect.value) {
          const mealPlanId = mealPlanSelect.value;

          const selectedOption = mealPlanSelect.options[mealPlanSelect.selectedIndex];
          const priceAttr = selectedOption?.dataset?.price;

          if (priceAttr) {
            amount = parseFloat(priceAttr);
          } else if (this.mealPlanPrices[mealPlanId]) {
            amount = parseFloat(this.mealPlanPrices[mealPlanId]);
          }
        }
        break;

      case 'laundry':
        const laundrySelect = document.getElementById('laundry_selection');
        if (laundrySelect && laundrySelect.value) {
          const laundrySlotId = laundrySelect.value;

          const selectedOption = laundrySelect.options[laundrySelect.selectedIndex];
          const priceAttr = selectedOption?.dataset?.price;

          if (priceAttr) {
            amount = parseFloat(priceAttr);
          } else if (this.laundryPrices[laundrySlotId]) {
            amount = parseFloat(this.laundryPrices[laundrySlotId]);
          }
        }
        break;
    }

    amountField.value = isNaN(amount) ? "0.00" : amount.toFixed(2);
  }
};

// Rating
const RatingModule = {
  init() {
  },

  showRoomRatingForm(bookingId) {
    document.getElementById('ratingBookingId').value = bookingId;
    Utils.toggleForm('roomRatingForm');
  },

  showMealRatingForm(mealPlanId) {
    document.getElementById('ratingMealPlanId').value = mealPlanId;
    Utils.toggleForm('mealRatingForm');
  },

  hideRatingForm() {
    document.getElementById('roomRatingForm').style.display = 'none';
    document.getElementById('mealRatingForm').style.display = 'none';
    document.getElementById('form-overlay').style.display = 'none';
  }
};

// Navigation
const NavModule = {
  init() {
    this.initSidebar();
    document.addEventListener('click', this.handleDocumentClick);
  },

  initSidebar() {
    const toggleSidebar = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (toggleSidebar && sidebar) {
      toggleSidebar.addEventListener('click', this.toggleNav);

      if (overlay) {
        overlay.addEventListener('click', this.toggleNav);
      }

      if (!sidebar.classList.contains('sidebar-hidden')) {
        sidebar.classList.add('sidebar-hidden');
      }
    }

    const notificationBtn = document.querySelector('.quick-action-btn:nth-child(1)');
    if (notificationBtn) {
      notificationBtn.addEventListener('click', function () {
        alert('Notifications would appear here');
      });
    }

    const settingsBtn = document.querySelector('.quick-action-btn:nth-child(3)');
    if (settingsBtn) {
      settingsBtn.addEventListener('click', function () {
        alert('Quick settings would appear here');
      });
    }
  },

  toggleNav() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('overlay');

    if (sidebar) {
      sidebar.classList.toggle('sidebar-hidden');

      if (mainContent) {
        mainContent.classList.toggle('expanded');
      }

      if (overlay) {
        overlay.classList.toggle('active');
      }

      if (!sidebar.classList.contains('sidebar-hidden') && mainContent) {
        NavModule.disableMainContentInteraction();
      } else if (mainContent) {
        NavModule.enableMainContentInteraction();
      }
    }
  },

  disableMainContentInteraction() {
    const mainContent = document.getElementById('mainContent');
    if (!mainContent) return;

    const links = mainContent.querySelectorAll('a, button, input, select, textarea');
    links.forEach(link => {
      link.setAttribute('tabindex', '-1');
      link.setAttribute('aria-hidden', 'true');
    });
  },

  enableMainContentInteraction() {
    const mainContent = document.getElementById('mainContent');
    if (!mainContent) return;

    const links = mainContent.querySelectorAll('a, button, input, select, textarea');
    links.forEach(link => {
      link.removeAttribute('tabindex');
      link.removeAttribute('aria-hidden');
    });
  },

  toggleSubmenu(element) {
    if (!element) return;

    const submenu = element.nextElementSibling;
    if (!submenu) return;

    element.classList.toggle('open');
    submenu.classList.toggle('active');

    const allSubmenus = document.querySelectorAll('.submenu.active');
    const allDropdowns = document.querySelectorAll('.has-dropdown.open');

    allSubmenus.forEach(menu => {
      if (menu !== submenu) {
        menu.classList.remove('active');
      }
    });

    allDropdowns.forEach(dropdown => {
      if (dropdown !== element) {
        dropdown.classList.remove('open');
      }
    });
  },

  handleDocumentClick(event) {
    if (!event.target.closest('.has-dropdown') && !event.target.closest('.submenu')) {
      const allSubmenus = document.querySelectorAll('.submenu.active');
      const allDropdowns = document.querySelectorAll('.has-dropdown.open');

      allSubmenus.forEach(menu => {
        menu.classList.remove('active');
      });

      allDropdowns.forEach(dropdown => {
        dropdown.classList.remove('open');
      });
    }
  }
};

// Financial
const FinancialModule = {
  init() {
  },

  initFinancialCards() {
    const firstCardHeader = document.querySelector('.financial-card-header');
    if (firstCardHeader) {
      const cardId = firstCardHeader.getAttribute('onclick');
      if (cardId) {
        const match = cardId.match(/['"](.*?)['"]/);
        if (match && match[1]) {
          this.toggleFinancialCard(match[1]);
        }
      }
    }
  },

  toggleFinancialCard(cardId) {
    const cardBody = document.getElementById(cardId);
    if (cardBody) {
      if (cardBody.classList.contains('active')) {
        cardBody.classList.remove('active');
      } else {
        cardBody.classList.add('active');
      }
    }
  }
};

// Deposit
const DepositModule = {
  init() {
    document.addEventListener('DOMContentLoaded', () => {
      this.setupDepositStatusHandlers();
    });
  },

  updateDepositForm(status, depositId, maxAmount) {
    const refundFields = document.getElementById(`refund-fields-${depositId}`);
    const withholdingFields = document.getElementById(`withholding-fields-${depositId}`);
    const refundedAmountField = document.getElementById(`refunded_amount_${depositId}`);

    if (status === 'partially_refunded' || status === 'fully_refunded') {
      refundFields.style.display = 'block';

      if (status === 'fully_refunded') {
        refundedAmountField.value = maxAmount;
      }
    } else {
      refundFields.style.display = 'none';
    }

    if (status === 'withheld' || status === 'partially_refunded') {
      withholdingFields.style.display = 'block';
    } else {
      withholdingFields.style.display = 'none';
    }

    const form = document.getElementById(`form-${depositId}`);
    if (form) {
      if (status === 'partially_refunded' || status === 'fully_refunded') {
        form.action = '../include/refund.php';
      } else {
        form.action = 'deposits.php';
      }
    }
  },

  setupDepositStatusHandlers() {
    const statusDropdowns = document.querySelectorAll('select[id^="status_"]');
    statusDropdowns.forEach(dropdown => {
      const depositId = dropdown.id.replace('status_', '');

      dropdown.addEventListener('change', () => {
        const form = document.getElementById(`form-${depositId}`);
        const status = dropdown.value;

        if (status === 'partially_refunded' || status === 'fully_refunded') {
          form.action = '../include/refund.php';
        } else {
          form.action = 'deposits.php';
        }
      });
    });
  },

  initDepositFormHandlers() {
    this.setupDepositStatusHandlers();
  }
};

// Login
const LoginModule = {
  init() {
  },

  toggleForms(show2FA) {
    document.getElementById('login-form').style.display = show2FA ? 'none' : 'block';
    document.getElementById('2fa-form').style.display = show2FA ? 'block' : 'none';
  },

  // Password Reset Functions
  showPasswordRequirements() {
    const popup = document.getElementById('password-requirements');
    if (popup) popup.style.display = 'block';
  },

  hidePasswordRequirements() {
    const popup = document.getElementById('password-requirements');
    if (popup) popup.style.display = 'none';
  },

  showConfirmPasswordTip() {
    const popup = document.getElementById('confirm-password-tip');
    if (popup) popup.style.display = 'block';
  },

  hideConfirmPasswordTip() {
    const popup = document.getElementById('confirm-password-tip');
    if (popup) popup.style.display = 'none';
  },

  validatePasswordStrength() {
    const password = document.getElementById('new_password')?.value || '';
    const confirmPassword = document.getElementById('confirm_password')?.value || '';
    const minLength = document.getElementById('min-length');
    const uppercase = document.getElementById('uppercase');
    const lowercase = document.getElementById('lowercase');
    const special = document.getElementById('special');
    const passwordMatch = document.getElementById('password-match');
    const confirmPasswordMatch = document.getElementById('confirm-password-match');

    if (minLength) {
      if (password.length >= 5) {
        minLength.classList.add('valid');
      } else {
        minLength.classList.remove('valid');
      }
    }

    if (uppercase) {
      if (/[A-Z]/.test(password)) {
        uppercase.classList.add('valid');
      } else {
        uppercase.classList.remove('valid');
      }
    }

    if (lowercase) {
      if (/[a-z]/.test(password)) {
        lowercase.classList.add('valid');
      } else {
        lowercase.classList.remove('valid');
      }
    }

    if (special) {
      if (/[^a-zA-Z0-9]/.test(password)) {
        special.classList.add('valid');
      } else {
        special.classList.remove('valid');
      }
    }

    if (passwordMatch && confirmPasswordMatch) {
      if (password === confirmPassword && password !== '') {
        passwordMatch.classList.add('valid');
        confirmPasswordMatch.classList.add('valid');
      } else {
        passwordMatch.classList.remove('valid');
        confirmPasswordMatch.classList.remove('valid');
      }
    }
  },

  validateResetForm() {
    const password = document.getElementById('new_password')?.value || '';
    const confirmPassword = document.getElementById('confirm_password')?.value || '';
    const formErrors = document.getElementById('form-errors');

    let isValid = true;
    const errors = [];

    if (password.length < 5) {
      errors.push("Password must be at least 5 characters long");
      isValid = false;
    }

    if (!/[A-Z]/.test(password)) {
      errors.push("Password must contain at least one uppercase letter");
      isValid = false;
    }

    if (!/[a-z]/.test(password)) {
      errors.push("Password must contain at least one lowercase letter");
      isValid = false;
    }

    if (!/[^a-zA-Z0-9]/.test(password)) {
      errors.push("Password must contain at least one special character");
      isValid = false;
    }

    if (password !== confirmPassword) {
      errors.push("Passwords do not match");
      isValid = false;
    }

    if (!isValid && formErrors) {
      formErrors.innerHTML = errors.join('<br>');
      formErrors.style.display = 'block';
      return false;
    }

    return true;
  }
};

// settings
const SettingsModule = {
  init() {
    this.initializeEventListeners();
  },

  initializeEventListeners() {
    document.addEventListener('DOMContentLoaded', function () {
    });
  },

  showPasswordPrompt(action) {
    document.getElementById('action-type').value = action;
    document.getElementById('password-prompt').style.display = 'block';
  },

  confirmDisable2FA() {
    if (confirm("Are you sure you want to disable 2FA?")) {
      document.getElementById('disable-2fa-form').submit();
      return true;
    }
    return false;
  }
};

// Main
document.addEventListener('DOMContentLoaded', function () {
  console.log("LuckyNest initializing...");

  Utils.hideAllElements(CONFIG.selectors.forms.edit);
  Utils.hideAllElements(CONFIG.selectors.forms.add);

  UserManagementModule.init();
  MealModule.init();
  LaundryModule.init();
  BookingModule.init();
  RatingModule.init();
  NavModule.init();
  FinancialModule.init();
  DepositModule.init();
  NotificationModule.init();
  LoginModule.init();
  SettingsModule.init();

  PaymentModule.init({
    roomRates: window.roomRates || {},
    mealPlanPrices: window.mealPlanPrices || {},
    laundryPrices: window.laundryPrices || {}
  });

  console.log("LuckyNest initialized successfully");
});

if (typeof LuckyNest === 'undefined') {
  window.LuckyNest = {};
}

window.LuckyNest = {
  toggleForm: Utils.toggleForm,
  playNotificationSound: Utils.playNotificationSound,

  confirmRoleChange: UserManagementModule.confirmRoleChange,

  updateBookingDetails: PaymentModule.updateBookingDetails,
  showPaymentForm: PaymentModule.showPaymentForm,
  calculateAmount: PaymentModule.calculateAmount,

  showRoomRatingForm: RatingModule.showRoomRatingForm,
  showMealRatingForm: RatingModule.showMealRatingForm,
  hideRatingForm: RatingModule.hideRatingForm,

  toggleNav: NavModule.toggleNav,
  toggleSubmenu: NavModule.toggleSubmenu,
  initSidebar: NavModule.initSidebar,

  toggleFinancialCard: FinancialModule.toggleFinancialCard,
  initFinancialCards: FinancialModule.initFinancialCards,

  updateDepositForm: DepositModule.updateDepositForm,
  initDepositFormHandlers: DepositModule.initDepositFormHandlers,

  updateDayOptions: MealModule.updateDayOptions,

  toggleDeleteLaundryForm: LaundryModule.toggleDeleteLaundryForm,
  toggleEndDateField: LaundryModule.toggleEndDateField,
  initLaundryCalendar: LaundryModule.initLaundryCalendar,

  toggleForms: LoginModule.toggleForms,
  showPasswordRequirements: LoginModule.showPasswordRequirements,
  hidePasswordRequirements: LoginModule.hidePasswordRequirements,
  showConfirmPasswordTip: LoginModule.showConfirmPasswordTip,
  hideConfirmPasswordTip: LoginModule.hideConfirmPasswordTip,
  validatePasswordStrength: LoginModule.validatePasswordStrength,
  validateResetForm: LoginModule.validateResetForm,

  showPasswordPrompt: SettingsModule.showPasswordPrompt,
  confirmDisable2FA: SettingsModule.confirmDisable2FA
};