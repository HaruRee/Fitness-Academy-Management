// Auto-logout functionality
let inactivityTime = function () {
  let time;
  const logoutAfter = 5 * 60 * 1000; // 5 minutes in milliseconds

  // Events that reset the timer
  window.onload = resetTimer;
  document.onmousemove = resetTimer;
  document.onkeydown = resetTimer;
  document.onclick = resetTimer;
  document.onscroll = resetTimer;
  document.ontouchstart = resetTimer;

  function logout() {
    // Display warning message
    const warningTime = 30000; // 30 seconds warning before logout
    const warningDiv = document.createElement("div");
    warningDiv.className = "logout-warning";
    warningDiv.innerHTML = `
            <div class="logout-warning-content">
                <i class="fas fa-exclamation-triangle"></i>
                <p>You will be logged out in 30 seconds due to inactivity.</p>
                <button id="stayLoggedIn" class="btn btn-primary">Stay Logged In</button>
            </div>
        `;
    document.body.appendChild(warningDiv);

    // Add event listener to the button
    document
      .getElementById("stayLoggedIn")
      .addEventListener("click", function () {
        resetTimer();
        document.body.removeChild(warningDiv);
      });

    // Start warning timer
    const logoutTimer = setTimeout(function () {
      window.location.href = "logout.php";
    }, warningTime);

    // If user interacts, cancel the logout
    resetTimer = function () {
      clearTimeout(logoutTimer);
      clearTimeout(time);
      time = setTimeout(logout, logoutAfter);

      // Remove warning if it exists
      if (document.body.contains(warningDiv)) {
        document.body.removeChild(warningDiv);
      }
    };
  }

  function resetTimer() {
    clearTimeout(time);
    time = setTimeout(logout, logoutAfter);
  }
};

// Add logout warning styling
const style = document.createElement("style");
style.innerHTML = `
.logout-warning {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}
.logout-warning-content {
    background-color: white;
    border-radius: 8px;
    padding: 1.5rem;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
.logout-warning i {
    font-size: 2.5rem;
    color: var(--warning-color);
    margin-bottom: 1rem;
}
.logout-warning p {
    margin-bottom: 1.5rem;
    font-size: 1rem;
}
`;
document.head.appendChild(style);

// Initialize inactivity timer
inactivityTime();
