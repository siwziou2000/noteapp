<!DOCTYPE html>
<html lang="el">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />

  <style>
    /* RESET */
    * {
       margin: 0; 
       padding: 0; 
       box-sizing: border-box; 
      }
    ul {
       list-style: none;
       }
    a {
       text-decoration: none;
        color: inherit;
       }
    button { 
      background: none;
       border: none; 
       font: inherit; 
       color: inherit; 
       cursor: pointer; 
      }

    /* BASE STYLES */
    body {
      background-color: #e8f0f7;
      font-family: 'Inter', sans-serif;
      padding-top: 110px; /* Χώρος για το fixed header */
    }

    .header {
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }

    /* TOP BAR - Ρυθμίσεις & Logout */
    .top-bar {
      background-color: rgb(39, 44, 51);
      color: rgba(255, 255, 255, 0.6);
      font-size: 12px;
    }

    .top-bar__content {
      height: 35px;
      max-width: 1200px;
      padding: 0 30px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .top-bar__links { display: flex; gap: 20px; }
    .top-bar__links a:hover { color: #fff; }

    /* BOTTOM BAR - Logo, Nav, Search */
    .bottom-bar {
       background-color: #ffc107;
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }

    .bottom-bar__content {
      min-height: 70px;
      max-width: 1200px;
      padding: 0 30px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: fit-content;
    }

    /* SEARCH FORM */
    .search-container { flex: 1; max-width: 400px; }
    .search-form {
      display: flex;
      background: rgba(0,0,0,0.05);
      border-radius: 20px;
      padding: 5px 15px;
      border: 1px solid rgba(0,0,0,0.1);
    }
    .search-input {
      background: none;
      border: none;
      color: #000;
      padding: 5px;
      width: 100%;
      outline: none;
    }
    .search-input::placeholder {
      color: rgba(0, 0, 0, 0.5);
    }
    .search-btn { color: #0071e3; font-size: 14px; }

    /* NAVIGATION */
    .nav__list { display: flex; column-gap: 25px; align-items: center; }
    .nav__link {
      font-size: 18px;
      display: block;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .nav__link:hover { color: #fff; }
    .nav__link.active { color: #ffc107; font-weight: bold; }

    .btn-contact {
      background-color: #0071e3;
      color: #fff;
      padding: 8px 18px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    /* MOBILE MENU */
    .hamburger { display: none; cursor: pointer; }
    
    .bar { height: 2px; width: 25px; background: #fff; margin: 5px 0; transition: 0.3s; }

    @media (max-width: 992px) {
      .nav {
        position: fixed;
        top: 105px;
        right: -100%;
        background-color: rgb(19, 22, 26);
        width: 100%;
        height: calc(100vh - 105px);
        transition: 0.4s;
        padding: 40px;
        z-index: 999;
      }
      .nav--open { right: 0; }
      .nav__list { flex-direction: column; row-gap: 30px; }
      .hamburger { display: block; }
      .search-container { max-width: 200px; }
    }

    @media (max-width: 600px) {
      .search-container { 
        display: block;
        width: 100%;
        margin-top: 10px;
      }
    }
  </style>
  
</head>

<body>

  <header class="header">
    <div class="top-bar">
      <div class="top-bar__content">
        <div class="top-bar__info">
          
        </div>
        <div class="top-bar__links">
            <a href="/noteapp/api/canva/home.php">Η σελιδα μου</a>
          <a href="/noteapp/api/canva/preferences_website.php"><i class="fa-solid fa-gear"></i> Ρυθμίσεις</a>
          <a href="/noteapp/api/canva/logout.php" style="color: #ff6b6b;"><i class="fa-solid fa-power-off"></i> Έξοδος</a>
        </div>
      </div>
    </div>

    <div class="bottom-bar">
      <div class="bottom-bar__content">
        <a href="home.php" class="logo">
          <i class="fa-solid fa-cloud-bolt" style="color: #0071e3; font-size: 24px;"></i>
          <span>NoteApp</span>
        </a>

        <?php 
        // Ελέγχοme an emadinatai mono sto home.php to search bar 
        $current_page = basename($_SERVER['PHP_SELF']);
        $is_home_page = ($current_page === 'home.php');
        
        if ($is_home_page): 
        ?>
        <div class="search-container">
          <form class="search-form" id="searchForm"> 
            <div class="search-input-wrapper" style="position: relative; display: flex; align-items: center; flex: 1;">
              <input type="text" class="search-input" id="searchInput" placeholder="Αναζήτηση καμβά..." 
                     style="width: 100%; padding-right: 35px;">
              
              <button type="button" id="clearSearch" 
                      style="position: absolute; right: 10px; display: none; border: none; background: none; color: #888; cursor: pointer;">
                <i class="fa-solid fa-circle-xmark"></i>
              </button>
            </div>
            
            <button type="submit" class="search-btn">
              <i class="fa-solid fa-magnifying-glass"></i>
            </button>
          </form>

          <div id="searchResults" class="search-results-dropdown"></div>
        </div>
        <?php endif; ?>

        <nav class="nav">
          <ul class="nav__list">
            <li><a class="nav__link active" href="/noteapp/api/canva/home.php">Η σελίδα μου</a></li>
            <li><a class="nav__link" href="/noteapp/api/canva/canvas.php">Οι καμβάδες μου</a></li>
            <li><a class="nav__link" href="/noteapp/api/canva/group/groups.php">Οι ομάδες μου</a></li>
            <li><a class="nav__link" href="/noteapp/api/canva/group/tasks/tasks.php">Οι εργασίες μου</a></li>
          </ul>
        </nav>

        <div class="hamburger">
          <div class="bar"></div>
          <div class="bar"></div>
          <div class="bar"></div>
        </div>
      </div>
    </div>
  </header>

  <main style="max-width: 1200px; margin: 40px auto; padding: 0 30px;">
    <div id="searchResults"></div>
  </main>

  <script>
    const navEl = document.querySelector('.nav');
    const hamburgerEl = document.querySelector('.hamburger');

    hamburgerEl.addEventListener('click', () => {
      navEl.classList.toggle('nav--open');
      // Animation για το hamburger
      const bars = document.querySelectorAll('.bar');
      bars[0].style.transform = navEl.classList.contains('nav--open') ? 'translateY(7px) rotate(45deg)' : '';
      bars[1].style.opacity = navEl.classList.contains('nav--open') ? '0' : '1';
      bars[2].style.transform = navEl.classList.contains('nav--open') ? 'translateY(-7px) rotate(-45deg)' : '';
    });

    // ΜΟΝΟ αν υπάρχει searchInput στο DOM (δηλαδή μόνο στο home.php)
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      const clearBtn = document.getElementById('clearSearch');
      const resultsDiv = document.getElementById('searchResults');

      // Εμφάνιση/Απόκρυψη του X ανάλογα με το αν έχει κείμενο
      searchInput.addEventListener('input', function() {
        if (clearBtn) {
          clearBtn.style.display = this.value.length > 0 ? 'block' : 'none';
        }
      });

      // Λειτουργία καθαρισμού όταν πατηθεί το X
      if (clearBtn) {
        clearBtn.addEventListener('click', function() {
          searchInput.value = '';
          if (resultsDiv) resultsDiv.innerHTML = '';
          this.style.display = 'none';
          searchInput.focus();
        });
      }
    }
  </script>

</body>
</html>