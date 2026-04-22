(function() {
  function ready(fn) {
    if (document.readyState != 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function() {
    // Modal HTML
    const modalHTML = `
      <div id="customModal" class="custom-modal">
        <div class="custom-modal-content">
          <div class="icon-circle" id="iconCircle">
            <div class="icon" id="icon"></div>
          </div>
          <h2 id="modalMessage">Message</h2>
          <button id="modalOkBtn">OK</button>
        </div>
      </div>
      <style>
        .custom-modal {
          display: none;
          position: fixed;
          z-index: 10000;
          left: 0; top: 0;
          width: 100%; height: 100%;
          background-color: rgba(0,0,0,0.5);
          backdrop-filter: blur(3px);
          font-family: Arial, sans-serif;
        }
        .custom-modal-content {
          background: #fff;
          margin: 15% auto;
          padding: 30px 40px;
          border-radius: 15px;
          text-align: center;
          width: 90%;
          max-width: 400px;
          box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        .icon-circle {
          width: 80px; height: 80px;
          margin: 0 auto 20px;
          border-radius: 50%;
          display: flex;
          justify-content: center;
          align-items: center;
          animation: popIn 0.5s ease forwards;
        }
        .icon {
          position: relative;
          width: 40px;
          height: 40px;
        }
        /* Checkmark lines */
        .icon.success .line1, .icon.success .line2 {
          position: absolute;
          background: white;
          transform-origin: left top;
        }
        .icon.success .line1 {
          width: 5px; height: 20px;
          left: 10px; top: 10px;
          transform: rotate(45deg);
        }
        .icon.success .line2 {
          width: 5px; height: 40px;
          left: 20px; top: 0px;
          transform: rotate(-45deg);
        }
        /* Cross lines */
        .icon.error .line1, .icon.error .line2 {
          position: absolute;
          width: 5px;
          height: 40px;
          background: white;
          top: 0; left: 17px;
        }
        .icon.error .line1 { transform: rotate(45deg); }
        .icon.error .line2 { transform: rotate(-45deg); }

        @keyframes popIn { 0% { transform: scale(0);} 70% { transform: scale(1.1);} 100% { transform: scale(1);} }

        #modalOkBtn {
          margin-top: 20px;
          padding: 10px 25px;
          border: none;
          background-color: #4BB543;
          color: white;
          border-radius: 8px;
          cursor: pointer;
          font-size: 16px;
          transition: 0.2s;
        }
        #modalOkBtn:hover { background-color: #3da033; }
        .custom-modal.error #modalOkBtn { background-color: #E74C3C; }
        .custom-modal.error #modalOkBtn:hover { background-color: #c0392b; }
      </style>
    `;

    const container = document.createElement('div');
    container.innerHTML = modalHTML;
    document.body.appendChild(container);

    // Show modal function
    window.showModal = function(message = "Message", type = "success") {
      const modal = document.getElementById("customModal");
      const modalMessage = document.getElementById("modalMessage");
      const okBtn = document.getElementById("modalOkBtn");
      const icon = document.getElementById("icon");
      const iconCircle = document.getElementById("iconCircle");

      // Clear previous icon
      icon.innerHTML = "";
      modal.classList.remove("error");

      // Set type
      if (type === "error") {
        modal.classList.add("error");
        iconCircle.style.background = "#E74C3C";
        icon.innerHTML = '<div class="line1"></div><div class="line2"></div>';
        icon.className = "icon error";
      } else {
        iconCircle.style.background = "#4BB543";
        icon.innerHTML = '<div class="line1"></div><div class="line2"></div>';
        icon.className = "icon success";
      }

      modalMessage.textContent = message;
      modal.style.display = "block";

      okBtn.onclick = function() {
        modal.style.display = "none";
      };
    };
  });
})();