// Rewards Page JavaScript
// Handles save/unsave toggle and user points checking

class RewardsManager {
    constructor() {
        this.savedRewards = [];
        this.userPoints = 0;
        this.init();
    }

    async init() {
        // Load saved rewards from database
        await this.loadSavedRewards();

        // Load user's total points
        this.loadUserPoints();

        // Initialize save buttons
        this.initializeSaveButtons();

        // Initialize redeem buttons
        this.initializeRedeemButtons();

        // Handle tab switching to show saved rewards
        this.handleTabSwitching();
    }

    async loadSavedRewards() {
        try {
            const response = await fetch('/rewards/saved');
            const data = await response.json();
            this.savedRewards = data.savedRewardIds || [];
        } catch (error) {
            console.error('Error loading saved rewards:', error);
            this.savedRewards = [];
        }
    }

    async loadUserPoints() {
        try {
            // Get user's total points from the navigation display
            const pointsElement = document.getElementById('totalPoints');
            if (pointsElement) {
                this.userPoints = parseInt(pointsElement.textContent) || 0;
            }

            // Alternatively, fetch from API if needed
            // const response = await fetch('/api/health-cycle/points-status');
            // const data = await response.json();
            // this.userPoints = data.total_points || 0;

            this.updateRedeemButtonStates();
        } catch (error) {
            console.error('Error loading user points:', error);
        }
    }

    initializeSaveButtons() {
        const saveButtons = document.querySelectorAll('.save-btn');

        saveButtons.forEach(button => {
            const rewardId = button.dataset.rewardId;

            // Set initial state based on saved rewards
            if (this.savedRewards.includes(parseInt(rewardId))) {
                this.setSavedState(button, true);
            }

            // Add click event listener
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleSave(rewardId, button);
            });
        });
    }

    async toggleSave(rewardId, button) {
        try {
            const response = await fetch('/rewards/toggle-save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ reward_id: rewardId })
            });

            const data = await response.json();

            if (data.saved) {
                if (!this.savedRewards.includes(parseInt(rewardId))) {
                    this.savedRewards.push(parseInt(rewardId));
                }
                this.setSavedState(button, true);
            } else {
                this.savedRewards = this.savedRewards.filter(id => id !== parseInt(rewardId));
                this.setSavedState(button, false);
            }

            this.updateSavedTab();
        } catch (error) {
            console.error('Error toggling save:', error);
        }
    }

    setSavedState(button, isSaved) {
        const heartIcon = button.querySelector('.heart-icon');
        if (heartIcon) {
            heartIcon.src = isSaved
                ? '/images/giftcards/heart_checked.png'
                : '/images/giftcards/heart_unchecked.png';
        }
    }

    updateRedeemButtonStates() {
        const redeemButtons = document.querySelectorAll('.redeem-btn');

        redeemButtons.forEach(button => {
            const requiredPoints = parseInt(button.dataset.points);
            const btnText = button.querySelector('.btn-text');

            if (this.userPoints >= requiredPoints) {
                button.classList.add('can-redeem');
                if (btnText) btnText.textContent = 'Redeem';
            } else {
                button.classList.remove('can-redeem');
                if (btnText) btnText.textContent = 'Not Yet';
            }
        });
    }

    initializeRedeemButtons() {
        // update all button states
        this.updateRedeemButtonStates();

        // add click listener
        const redeemButtons = document.querySelectorAll('.redeem-btn');

        redeemButtons.forEach(button => {
            const requiredPoints = parseInt(button.dataset.points);

            button.addEventListener('click', () => {
                if (this.userPoints >= requiredPoints) {
                    this.redeemReward(button, requiredPoints);
                } else {
                    this.showInsufficientPointsMessage(requiredPoints);
                }
            });
        });
    }

    async redeemReward(button, requiredPoints) {
        const card = button.closest('.reward-card');
        const rewardId = card.dataset.rewardId;
        const rewardName = card.querySelector('h3').textContent;

        const confirmed = confirm(`Redeem "${rewardName}" for ${requiredPoints} points?`);

        if (confirmed) {
            try {
                const response = await fetch('/rewards/redeem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ reward_id: rewardId })
                });

                const data = await response.json();

                if (data.success) {
                    // Update user points from server response
                    this.userPoints = data.new_points;
                    const pointsElement = document.getElementById('totalPoints');
                    if (pointsElement) {
                        pointsElement.textContent = this.userPoints;
                    }

                    this.updateRedeemButtonStates();
                    this.updateAvailableTab();
                    alert(`Successfully redeemed "${data.reward_name}"! Check your email for details.`); //TODO: but out of scope for this project, no e-mail notification implemented 
                } else {
                    alert(data.error || 'Failed to redeem reward');
                }
            } catch (error) {
                console.error('Error redeeming reward:', error);
                alert('An error occurred. Please try again.');
            }
        }
    }

    showInsufficientPointsMessage(requiredPoints) {
        const pointsNeeded = requiredPoints - this.userPoints;
        alert(`You need ${pointsNeeded} more points to redeem this reward. Keep earning points!`);
    }

    handleTabSwitching() {
        // Check if we're on the saved tab
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'all';

        // Update saved tab when switching tabs
        const navLinks = document.querySelectorAll('.rewards-nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const newTab = new URLSearchParams(window.location.search).get('tab');
                if (newTab === 'saved') {
                    this.updateSavedTab();
                }
                if (newTab === 'available') {
                    this.updateAvailableTab();
                }
            });
        });

        if (currentTab === 'saved') {
            this.updateSavedTab();
        }
        if (currentTab === 'available') {
            this.updateAvailableTab();
        }
    }
    updateAvailableTab() {
        const availableGrid = document.getElementById('availableRewardsGrid');
        if (!availableGrid) return;

        // Clear existing content
        availableGrid.innerHTML = '';

        // Get all rewards from template and filter affordable ones
        const templateContainer = document.getElementById('rewardsTemplate');
        const allCards = templateContainer.querySelectorAll('.reward-card');

        let affordableCount = 0;

        allCards.forEach(card => {
            const redeemBtn = card.querySelector('.redeem-btn');
            const requiredPoints = parseInt(redeemBtn.dataset.points);

            // Only show if user can afford it
            if (this.userPoints >= requiredPoints) {
                affordableCount++;
                const clonedCard = card.cloneNode(true);

                // Reinitialize the save button
                const saveBtn = clonedCard.querySelector('.save-btn');
                if (saveBtn) {
                    const rewardId = saveBtn.dataset.rewardId;
                    const isSaved = this.savedRewards.includes(parseInt(rewardId));
                    this.setSavedState(saveBtn, isSaved);
                    saveBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleSave(rewardId, saveBtn);
                    });
                }

                // Reinitialize the redeem button
                const clonedRedeemBtn = clonedCard.querySelector('.redeem-btn');
                if (clonedRedeemBtn) {
                    clonedRedeemBtn.classList.add('can-redeem');
                    const btnText = clonedRedeemBtn.querySelector('.btn-text');
                    if (btnText) btnText.textContent = 'Redeem';

                    clonedRedeemBtn.addEventListener('click', () => {
                        this.redeemReward(clonedRedeemBtn, requiredPoints);
                    });
                }

                availableGrid.appendChild(clonedCard);
            }
        });

          // Show message if no affordable rewards
      if (affordableCount === 0) {
          availableGrid.innerHTML = '<p class="no-saved-message">You don\'t have enough points yet. Keep earning!</p>';
      }
    }

    updateSavedTab() {
        const savedGrid = document.getElementById('savedRewardsGrid');
        if (!savedGrid) return;

        // Clear existing content
        savedGrid.innerHTML = '';

        if (this.savedRewards.length === 0) {
            savedGrid.innerHTML = '<p class="no-saved-message">You haven\'t saved any rewards yet. Browse the "All" tab and click the heart icon to save your favorites!</p>';
            return;
        }

        // Clone saved reward cards from the template
        this.savedRewards.forEach(rewardId => {
            const templateContainer = document.getElementById('rewardsTemplate');
            const originalCard = templateContainer.querySelector(`.reward-card[data-reward-id="${rewardId}"]`);

            if (originalCard) {
                const clonedCard = originalCard.cloneNode(true);

                // Reinitialize the save button for the cloned card
                const saveBtn = clonedCard.querySelector('.save-btn');
                if (saveBtn) {
                    this.setSavedState(saveBtn, true);
                    saveBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleSave(rewardId, saveBtn);
                    });
                }

                // Reinitialize the redeem button for the cloned card
                const redeemBtn = clonedCard.querySelector('.redeem-btn');
                if (redeemBtn) {
                    const requiredPoints = parseInt(redeemBtn.dataset.points);
                    redeemBtn.addEventListener('click', () => {
                        if (this.userPoints >= requiredPoints) {
                            this.redeemReward(redeemBtn, requiredPoints);
                        } else {
                            this.showInsufficientPointsMessage(requiredPoints);
                        }
                    });
                }

                savedGrid.appendChild(clonedCard);
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const rewardsManager = new RewardsManager();

    // Update points when navigation points update (if using live updates)
    window.addEventListener('pointsUpdated', () => {
        rewardsManager.loadUserPoints();
    });
});