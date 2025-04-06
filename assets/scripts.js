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
  },

  toggleDeleteLaundryForm(slotId) {
    const deleteOptions = document.getElementById(`delete-options-${slotId}`);
    if (deleteOptions) {
      deleteOptions.style.display = 'block';
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
    flatpickr(dateInput, {
      minDate: "today",
      dateFormat: "Y-m-d",
      onDayCreate: (dObj, dStr, fp, dayElem) => {
        const currentDate = dayElem.dateObj;
        const isBooked = this.isDateBooked(currentDate, bookedDates);

        dayElem.style.backgroundColor = isBooked ? "#ffcccc" : "#ffffff";
        if (isBooked) {
          dayElem.classList.add("booked-date");
          dayElem.style.color = "#666";
        }
      }
    });
  },

  isDateBooked(currentDate, bookedDates) {
    currentDate.setHours(0, 0, 0, 0);

    return bookedDates.some(booking => {
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
    ['rent_form', 'meal_plan_form', 'laundry_form'].forEach(formId => {
      const formElement = document.getElementById(formId);
      if (formElement) formElement.style.display = 'none';
    });

    const selectedForm = document.getElementById(`${type}_form`);
    if (selectedForm) selectedForm.style.display = 'block';

    const paymentTypeInputs = document.querySelectorAll('input[id="payment_type_hidden"]');
    paymentTypeInputs.forEach(input => {
      input.value = type;
    });

    setTimeout(() => this.calculateAmount(), 0);
  },

  calculateAmount() {
    const visibleForm = ['rent_form', 'meal_plan_form', 'laundry_form'].find(id => {
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
    // Any initialization code for ratings
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
    // Any initialization for financial module
  },

  initFinancialCards() {
    // Automatically show the first financial card
    const firstCardHeader = document.querySelector('.financial-card-header');
    if (firstCardHeader) {
      const cardId = firstCardHeader.getAttribute('onclick');
      if (cardId) {
        // Extract card ID from the onclick attribute
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

// Main
document.addEventListener('DOMContentLoaded', function () {
  console.log("LuckyNest initializing...");

  Utils.hideAllElements(CONFIG.selectors.forms.edit);
  Utils.hideAllElements(CONFIG.selectors.forms.add);

  MealModule.init();
  LaundryModule.init();
  BookingModule.init();
  RatingModule.init();
  NavModule.init();
  FinancialModule.init();

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
  toggleDeleteLaundryForm: LaundryModule.toggleDeleteLaundryForm,
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
  initFinancialCards: FinancialModule.initFinancialCards
};