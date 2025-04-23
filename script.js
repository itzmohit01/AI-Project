document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const dashboardContainer = document.querySelector('.dashboard-container');

    const showMessage = (elementId, message, isSuccess) => {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.className = 'message'; // Reset classes
            element.classList.add(isSuccess ? 'success' : 'error');
            element.style.display = 'block';
        }
    };

    const clearMessage = (elementId) => {
         const element = document.getElementById(elementId);
         if (element) {
             element.textContent = '';
             element.style.display = 'none';
         }
    }

    const handleFormSubmit = async (form, url, messageId) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearMessage(messageId);

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (result.success) {
                    showMessage(messageId, result.message, true);
                    if (url === 'login.php') {
                        window.location.href = 'dashboard.html';
                    } else if (url === 'register.php') {
                         setTimeout(() => { window.location.href = 'index.html'; }, 1500);
                    }
                } else {
                    showMessage(messageId, result.message, false);
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage(messageId, 'An unexpected error occurred.', false);
            }
        });
    };

    if (loginForm) {
        handleFormSubmit(loginForm, 'login.php', 'login-message');
    }

    if (registerForm) {
        handleFormSubmit(registerForm, 'register.php', 'register-message');
    }

    // Dashboard specific logic
    if (dashboardContainer) {
        const usernameDisplay = document.getElementById('username-display');
        const streakCount = document.getElementById('streak-count');
        const logWorkoutBtn = document.getElementById('log-workout-btn');
        const logMessage = document.getElementById('log-message');
        const chatHistory = document.getElementById('chat-history');
        const chatMessageInput = document.getElementById('chat-message');
        const sendChatBtn = document.getElementById('send-chat-btn');
        const chatError = document.getElementById('chat-error');

        const checkAuthentication = async () => {
            try {
                const response = await fetch('check_auth.php');
                const result = await response.json();
                if (!result.authenticated) {
                    window.location.href = 'index.html';
                } else {
                    loadDashboardData();
                }
            } catch (error) {
                console.error('Auth check failed:', error);
                 window.location.href = 'index.html'; // Redirect if auth check fails
            }
        };

         const loadDashboardData = async () => {
            try {
                const response = await fetch('get_user_data.php');
                const result = await response.json();
                if (result.success && result.data) {
                    usernameDisplay.textContent = result.data.username || 'User';
                    streakCount.textContent = result.data.current_streak || 0;
                } else {
                     console.error('Failed to load user data:', result.message);
                     if (!result.success && result.message === 'Not logged in.') {
                         window.location.href = 'index.html';
                     }
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            }
        };

        const logWorkout = async () => {
             clearMessage('log-message');
             logWorkoutBtn.disabled = true;
             logWorkoutBtn.textContent = 'Logging...';
             try {
                 const response = await fetch('log_workout.php', { method: 'POST' });
                 const result = await response.json();
                 if (result.success) {
                     streakCount.textContent = result.new_streak;
                     showMessage('log-message', result.message, true);
                     logWorkoutBtn.textContent = 'Workout Logged for Today!';
                 } else if (result.message === 'Workout already logged for today.') {
                      showMessage('log-message', result.message, false);
                      streakCount.textContent = result.new_streak; // Update streak display anyway
                      logWorkoutBtn.textContent = 'Already Logged Today';
                 }
                 else {
                     showMessage('log-message', result.message || 'Failed to log workout.', false);
                     logWorkoutBtn.disabled = false;
                     logWorkoutBtn.textContent = "Log Today's Workout";
                 }

             } catch (error) {
                 console.error('Error logging workout:', error);
                 showMessage('log-message', 'An error occurred while logging workout.', false);
                 logWorkoutBtn.disabled = false;
                 logWorkoutBtn.textContent = "Log Today's Workout";
             }
         };

        const addChatMessage = (sender, text) => {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(sender); // 'user' or 'ai' or 'typing'
            messageDiv.textContent = text;
            chatHistory.appendChild(messageDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight; // Scroll to bottom
        };

        const sendChatMessage = async () => {
            const messageText = chatMessageInput.value.trim();
            if (!messageText) return;

            addChatMessage('user', messageText);
            chatMessageInput.value = '';
            chatError.textContent = '';
            sendChatBtn.disabled = true;
            addChatMessage('typing', 'Sparky is thinking...'); // Add typing indicator

            try {
                 const response = await fetch('chat.php', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json' },
                     body: JSON.stringify({ message: messageText })
                 });
                 const result = await response.json();

                 // Remove typing indicator
                 const typingIndicator = chatHistory.querySelector('.message.typing');
                 if (typingIndicator) {
                     chatHistory.removeChild(typingIndicator);
                 }

                 if (result.success) {
                     addChatMessage('ai', result.reply);
                 } else {
                     addChatMessage('ai', result.reply || 'Sorry, something went wrong.'); // Show error reply if available
                     chatError.textContent = result.message || 'Failed to get reply.';
                     console.error("Chat Error:", result.message);
                 }

            } catch(error) {
                 console.error('Chat fetch error:', error);
                 const typingIndicator = chatHistory.querySelector('.message.typing');
                  if (typingIndicator) {
                      chatHistory.removeChild(typingIndicator);
                  }
                 addChatMessage('ai', 'Sorry, I could not connect to the AI coach right now.');
                 chatError.textContent = 'Network error sending message.';
            } finally {
                 sendChatBtn.disabled = false;
                 chatMessageInput.focus();
            }
        };

        logWorkoutBtn.addEventListener('click', logWorkout);
        sendChatBtn.addEventListener('click', sendChatMessage);
        chatMessageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });

        // Initial check and load
        checkAuthentication();
    }
});