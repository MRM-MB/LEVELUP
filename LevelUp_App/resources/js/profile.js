/**
 * Profile Height Editor
 * Handles the inline editing functionality for user height preferences
 */
class ProfileHeightEditor {
    constructor() {
        this.initializeElements();
        this.storeOriginalValues();
        this.attachEventListeners();
        this.setupButtonEffects();
        this.setupInputEffects();
        this.setupKeyboardShortcuts();
        this.autoHideSuccessMessage();
    }

    initializeElements() {
        // Control elements
        this.editBtn = document.getElementById('editHeightsBtn');
        this.saveControls = document.getElementById('saveControls');
        this.cancelBtn = document.getElementById('cancelBtn');
        this.form = document.getElementById('heightForm');
        
        // Display elements
        this.standingDisplay = document.getElementById('standingDisplay');
        this.sittingDisplay = document.getElementById('sittingDisplay');
        
        // Input elements
        this.standingInput = document.getElementById('standingInput');
        this.sittingInput = document.getElementById('sittingInput');
        
        // Unit elements
        this.standingUnit = document.querySelector('.height-setting:first-child .unit');
        this.sittingUnit = document.querySelector('.height-setting:last-child .unit');
        this.standingUnitEdit = document.getElementById('standingUnit');
        this.sittingUnitEdit = document.getElementById('sittingUnit');
    }

    storeOriginalValues() {
        this.originalStanding = this.standingInput.value;
        this.originalSitting = this.sittingInput.value;
    }

    attachEventListeners() {
        // Edit button
        this.editBtn.addEventListener('click', () => this.enterEditMode());
        
        // Cancel button
        this.cancelBtn.addEventListener('click', () => this.cancelEdit());
        
        // Form validation
        this.form.addEventListener('submit', (e) => this.validateForm(e));
    }

    setupButtonEffects() {
        const buttons = [this.editBtn, document.querySelector('.save-heights-btn'), this.cancelBtn];
        
        buttons.forEach(btn => {
            if (btn) {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                });
            }
        });
    }

    setupInputEffects() {
        [this.standingInput, this.sittingInput].forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = '#5a67d8';
                this.style.boxShadow = '0 0 0 3px rgba(139, 158, 255, 0.2)';
            });
            
            input.addEventListener('blur', function() {
                this.style.borderColor = 'var(--color-accent)';
                this.style.boxShadow = 'none';
            });
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (this.isInEditMode()) {
                if (e.key === 'Escape') {
                    this.cancelEdit();
                } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    this.form.submit();
                }
            }
        });
    }

    autoHideSuccessMessage() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.classList.add('fade-out');
                setTimeout(() => successAlert.remove(), 300);
            }, 5000);
        }
    }

    enterEditMode() {
        // Hide display elements
        this.standingDisplay.classList.add('hidden');
        this.sittingDisplay.classList.add('hidden');
        if (this.standingUnit) this.standingUnit.classList.add('hidden');
        if (this.sittingUnit) this.sittingUnit.classList.add('hidden');
        
        // Show input elements
        this.standingInput.classList.remove('hidden');
        this.sittingInput.classList.remove('hidden');
        this.standingUnitEdit.classList.remove('hidden');
        this.sittingUnitEdit.classList.remove('hidden');
        
        // Show save controls, hide edit button
        this.editBtn.classList.add('hidden');
        this.saveControls.classList.remove('hidden');
        
        // Focus and select first input
        this.standingInput.focus();
        this.standingInput.select();
    }

    cancelEdit() {
        // Restore original values
        this.standingInput.value = this.originalStanding;
        this.sittingInput.value = this.originalSitting;
        
        this.exitEditMode();
    }

    exitEditMode() {
        // Show display elements
        this.standingDisplay.classList.remove('hidden');
        this.sittingDisplay.classList.remove('hidden');
        if (this.standingUnit) this.standingUnit.classList.remove('hidden');
        if (this.sittingUnit) this.sittingUnit.classList.remove('hidden');
        
        // Hide input elements
        this.standingInput.classList.add('hidden');
        this.sittingInput.classList.add('hidden');
        this.standingUnitEdit.classList.add('hidden');
        this.sittingUnitEdit.classList.add('hidden');
        
        // Show edit button, hide save controls
        this.editBtn.classList.remove('hidden');
        this.saveControls.classList.add('hidden');
    }

    validateForm(e) {
        const standing = parseInt(this.standingInput.value);
        const sitting = parseInt(this.sittingInput.value);
        
        // Get limits from input attributes
        const minHeight = parseInt(this.standingInput.getAttribute('min')) || 60;
        const maxHeight = parseInt(this.standingInput.getAttribute('max')) || 200;
        
        // Check if standing height is greater than sitting height
        if (standing && sitting && standing <= sitting) {
            e.preventDefault();
            this.showValidationError('Standing height must be greater than sitting height!');
            this.standingInput.focus();
            return false;
        }
        
        // Validate standing height range
        if (standing && (standing < minHeight || standing > maxHeight)) {
            e.preventDefault();
            this.showValidationError(`Standing height must be between ${minHeight}cm and ${maxHeight}cm!`);
            this.standingInput.focus();
            return false;
        }
        
        // Validate sitting height range
        if (sitting && (sitting < minHeight || sitting > maxHeight)) {
            e.preventDefault();
            this.showValidationError(`Sitting height must be between ${minHeight}cm and ${maxHeight}cm!`);
            this.sittingInput.focus();
            return false;
        }
        
        return true;
    }

    showValidationError(message) {
        // Create or update error message
        let errorDiv = document.querySelector('.validation-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error validation-error';
            errorDiv.style.marginTop = '1rem';
            this.saveControls.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        
        // Auto-hide after 4 seconds
        setTimeout(() => {
            if (errorDiv) {
                errorDiv.remove();
            }
        }, 4000);
    }

    isInEditMode() {
        return this.editBtn.classList.contains('hidden');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new ProfileHeightEditor();
});