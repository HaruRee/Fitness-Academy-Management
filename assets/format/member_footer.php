<footer class="mt-auto" style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); border-top: 2px solid #d62328; color: #b3b3b3; padding: 40px 20px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-shadow: 0 -4px 20px rgba(0,0,0,0.3);">
  <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
    <div>
      <h3 style="color: #ffffff; margin-bottom: 15px; font-size: 1.3rem; font-weight: 700; position: relative; padding-bottom: 8px;">
        <span style="border-bottom: 3px solid #d62328; padding-bottom: 5px;">Fitness Academy</span>
      </h3>
      <p style="margin: 0; line-height: 1.6; color: #cccccc; font-size: 0.95rem;">
        Your premiere fitness destination in Caloocan City.<br>
        We're dedicated to helping you achieve your fitness<br>
        goals in a supportive and motivating environment.
      </p>
    </div>
    
    <div>
      <h3 style="color: #ffffff; margin-bottom: 15px; font-size: 1.3rem; font-weight: 700; position: relative; padding-bottom: 8px;">
        <span style="border-bottom: 3px solid #d62328; padding-bottom: 5px;">Contact Info</span>
      </h3>
      <div style="display: flex; flex-direction: column; gap: 8px;">
        <p style="margin: 0; display: flex; align-items: center; color: #cccccc; font-size: 0.95rem;">
          <i class="fas fa-phone-alt" style="color: #d62328; margin-right: 10px; width: 15px;"></i>
          0917 700 4373
        </p>
        <p style="margin: 0; display: flex; align-items: center; color: #cccccc; font-size: 0.95rem;">
          <i class="fas fa-envelope" style="color: #d62328; margin-right: 10px; width: 15px;"></i>
          fitnessacademycaloocan@gmail.com
        </p>
      </div>
    </div>
    
    <div>
      <h3 style="color: #ffffff; margin-bottom: 15px; font-size: 1.3rem; font-weight: 700; position: relative; padding-bottom: 8px;">
        <span style="border-bottom: 3px solid #d62328; padding-bottom: 5px;">Follow Us</span>
      </h3>
      <div style="display: flex; gap: 15px; align-items: center;">
        <a href="https://web.facebook.com/FitnessAcademyCaloocan" target="_blank" 
           style="color: #b3b3b3; text-decoration: none; font-size: 1.5rem; width: 40px; height: 40px; 
                  display: flex; align-items: center; justify-content: center; border-radius: 50%; 
                  background: rgba(214, 35, 40, 0.1); transition: all 0.3s ease; border: 2px solid transparent;">
          <i class="fab fa-facebook-f"></i>
        </a>
        <a href="https://www.instagram.com/_fitnessacademyofficial?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" 
           style="color: #b3b3b3; text-decoration: none; font-size: 1.5rem; width: 40px; height: 40px; 
                  display: flex; align-items: center; justify-content: center; border-radius: 50%; 
                  background: rgba(214, 35, 40, 0.1); transition: all 0.3s ease; border: 2px solid transparent;">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="https://www.tiktok.com/@fitnessacademyofficial?is_from_webapp=1&sender_device=pc" target="_blank" 
           style="color: #b3b3b3; text-decoration: none; font-size: 1.5rem; width: 40px; height: 40px; 
                  display: flex; align-items: center; justify-content: center; border-radius: 50%; 
                  background: rgba(214, 35, 40, 0.1); transition: all 0.3s ease; border: 2px solid transparent;">
          <i class="fab fa-tiktok"></i>
        </a>
      </div>
    </div>
  </div>
  
  <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #404040; color: #666666; font-size: 0.9rem;">
    © 2025 Fitness Academy. All rights reserved.
  </div>
  
  <style>
    footer a:hover {
      color: #ffffff !important;
      background: rgba(214, 35, 40, 0.2) !important;
      border-color: #d62328 !important;
      transform: translateY(-2px) scale(1.1);
    }
    
    @media (max-width: 768px) {
      footer {
        padding: 30px 15px 15px !important;
      }
      
      footer h3 {
        font-size: 1.1rem !important;
        margin-bottom: 12px !important;
      }
      
      footer p {
        font-size: 0.9rem !important;
      }
      
      footer div[style*="display: flex; gap: 15px"] {
        justify-content: center;
        margin-top: 15px;
      }
    }
  </style>
</footer>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const menuToggle = document.querySelector(".menu-toggle");
    const navLinks = document.querySelector("nav");

    if (menuToggle && navLinks) {
      menuToggle.addEventListener("click", () => {
        navLinks.classList.toggle("active");
      });
    }
  });
</script>

</body>

</html>