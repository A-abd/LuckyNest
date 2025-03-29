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
  toggleElement(formName) {
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
  },

  updateBookingDetails() {
    const select = document.getElementById('booking_selection');
    if (!select) return;

    if (select.value === "") {
      ['amount', 'check_in_date', 'check_out_date', 'room_id'].forEach(id => {
        document.getElementById(id).value = "";
      });
      return;
    }

    const option = select.options[select.selectedIndex];

    document.getElementById('room_id').value = option.dataset.roomId;
    document.getElementById('check_in_date').value = option.dataset.checkInDate;
    document.getElementById('check_out_date').value = option.dataset.checkOutDate;

    this.calculateAmount();
  },

  showPaymentForm(type) {
    ['rent_form', 'meal_plan_form', 'laundry_form'].forEach(formId => {
      document.getElementById(formId).style.display = 'none';
    });

    document.getElementById(`${type}_form`).style.display = 'block';
    document.getElementById('payment_type_hidden').value = type;
  },

  calculateAmount() {
    const paymentTypeInput = document.querySelector('input[name="payment_type"]');
    if (!paymentTypeInput) return;

    const paymentType = paymentTypeInput.value;
    const amountField = document.getElementById('amount');
    if (!amountField) return;

    switch (paymentType) {
      case 'rent':
        const roomId = document.getElementById('room_id').value;
        if (roomId && this.roomRates[roomId]) {
          amountField.value = this.roomRates[roomId].toFixed(2);
        }
        break;

      case 'meal_plan':
        const mealPlanSelect = document.getElementById('meal_plan_selection');
        if (mealPlanSelect && mealPlanSelect.value) {
          const mealPlanId = mealPlanSelect.value;
          amountField.value = this.mealPlanPrices[mealPlanId].toFixed(2);
        }
        break;

      case 'laundry':
        const laundrySelect = document.getElementById('laundry_selection');
        if (laundrySelect && laundrySelect.value) {
          const laundrySlotId = laundrySelect.value;
          amountField.value = this.laundryPrices[laundrySlotId].toFixed(2);
        }
        break;
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

  PaymentModule.init({
    roomRates: window.roomRates || {},
    mealPlanPrices: window.mealPlanPrices || {},
    laundryPrices: window.laundryPrices || {}
  });

  console.log("LuckyNest initialized successfully");
});

window.LuckyNest = {
  toggleForm: Utils.toggleElement,
  toggleDeleteLaundryForm: LaundryModule.toggleDeleteLaundryForm,
  updateBookingDetails: PaymentModule.updateBookingDetails,
  showPaymentForm: PaymentModule.showPaymentForm,
  calculateAmount: PaymentModule.calculateAmount.apply
};
/*
// CSS Funtionality
function toggleAddForm() {
  const addForm = document.getElementById('add-form');
  const overlay = document.getElementById('form-overlay');

  if (addForm.style.display === 'none' || addForm.style.display === '') {
    overlay.style.display = 'block';
    addForm.style.display = 'block';
    setTimeout(() => {
      overlay.classList.add('active');
      addForm.classList.add('active');
    }, 10);
  } else {
    overlay.classList.remove('active');
    addForm.classList.remove('active');
    setTimeout(() => {
      overlay.style.display = 'none';
      addForm.style.display = 'none';
    }, 300);
  }
}

function toggleEditForm(id) {
  const editForm = document.getElementById(`edit-form-${id}`);
  const overlay = document.getElementById('form-overlay');

  if (editForm.style.display === 'none' || editForm.style.display === '') {
    overlay.style.display = 'block';
    editForm.style.display = 'block';
    setTimeout(() => {
      overlay.classList.add('active');
      editForm.classList.add('active');
    }, 10);
  } else {
    overlay.classList.remove('active');
    editForm.classList.remove('active');
    setTimeout(() => {
      overlay.style.display = 'none';
      editForm.style.display = 'none';
    }, 300);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  if (!document.getElementById('form-overlay')) {
    const overlay = document.createElement('div');
    overlay.id = 'form-overlay';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        const activeForms = document.querySelectorAll('#add-form.active, .rooms-edit-form.active');
        activeForms.forEach(form => {
          form.classList.remove('active');
          setTimeout(() => {
            form.style.display = 'none';
          }, 300);
        });
        overlay.classList.remove('active');
        setTimeout(() => {
          overlay.style.display = 'none';
        }, 300);
      }
    });
  }

  hideAllEditForms();
  hideAddForm();
});

function hideAllEditForms() {
  const editForms = document.querySelectorAll('.rooms-edit-form');
  editForms.forEach(form => {
    form.style.display = 'none';
  });
}

function hideAddForm() {
  const addForm = document.getElementById('add-form');
  if (addForm) {
    addForm.style.display = 'none';
  }
}
*/