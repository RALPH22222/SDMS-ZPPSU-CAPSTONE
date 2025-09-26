<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZPPSU Student Disciplinary Management System</title>
    <link rel="stylesheet" href="css/output.css">
    <link rel="stylesheet" href="css/brand-fallback.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
<body class="bg-white text-dark">
    <!-- Header / Navigation -->
    <header class="bg-white shadow-md fixed w-full top-0 z-50 border-b-2 border-primary">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <a href="#home" class="flex items-center space-x-3">
                    <img src="src/images/Logo.png" alt="ZPPSU Logo" class="h-10 w-auto">
                    <span class="text-primary font-bold text-lg hidden sm:block">ZPPSU Student Disciplinary Management System</span>
                </a>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="#home" class="py-2 text-dark hover:text-primary font-medium transition-colors">Home</a>
                    <a href="#features" class="py-2 text-dark hover:text-primary font-medium transition-colors">Features</a>
                    <a href="#about" class="py-2 text-dark hover:text-primary font-medium transition-colors">About</a>
                    <a href="#contact" class="py-2 text-dark hover:text-primary font-medium transition-colors">Contact</a>
                    <a href="pages/Auth/login.php" class="btn btn-primary py-2">
                        Log In
                    </a>
                </nav>
                <button id="mobileMenuButton" class="md:hidden text-primary text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div id="mobileMenu" class="hidden md:hidden mt-4 pb-2">
                <div class="flex flex-col space-y-3">
                    <a href="#home" class="text-dark hover:text-primary font-medium">Home</a>
                    <a href="#features" class="text-dark hover:text-primary font-medium">Features</a>
                    <a href="#about" class="text-dark hover:text-primary font-medium">About</a>
                    <a href="#contact" class="text-dark hover:text-primary font-medium">Contact</a>
                    <a href="pages/Auth/login.php" class="btn btn-primary w-full mt-2 py-2 text-center">
                        Log In
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section with Enhanced Carousel -->
    <section id="home" class="relative text-white overflow-hidden">
        <div id="heroCarousel" class="relative w-full h-screen max-h-[90vh]">
            <!-- Carousel Items -->
            <div class="carousel-track flex h-full transition-transform duration-1000 ease-in-out">
                <!-- Slide 1 -->
                <div class="carousel-item min-w-full h-full relative">
                    <img src="src/images/1-b.png" alt="Campus life" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/40"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-center px-4">
                        <div class="max-w-4xl mx-auto">
                            <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-fadeInUp">Welcome to ZPPSU</h1>
                            <p class="text-xl md:text-2xl mb-8 max-w-2xl mx-auto">Ensuring a disciplined and conducive learning environment</p>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <a href="pages/Auth/login.php" class="btn btn-primary px-8 py-3 text-lg font-semibold hover:bg-primary-dark transition-all transform hover:scale-105">
                                    Get Started <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                                <a href="#features" class="btn btn-outline-white px-8 py-3 text-lg font-semibold hover:bg-white/10 transition-all">
                                    Learn More
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Slide 2 -->
                <div class="carousel-item min-w-full h-full relative">
                    <img src="src/images/5-b.png" alt="Focused study" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/40"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-center px-4">
                        <div class="max-w-4xl mx-auto">
                            <h1 class="text-4xl md:text-6xl font-bold mb-6">Transparent Disciplinary Process</h1>
                            <p class="text-xl md:text-2xl mb-8 max-w-2xl mx-auto">Track and manage cases with complete visibility and accountability</p>
                            <a href="#features" class="btn btn-primary px-8 py-3 text-lg font-semibold hover:bg-primary-dark transition-all transform hover:scale-105">
                                Explore Features
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Slide 3 -->
                <div class="carousel-item min-w-full h-full relative">
                    <img src="src/images/8-b.png" alt="Team collaboration" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/40"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-center px-4">
                        <div class="max-w-4xl mx-auto">
                            <h1 class="text-4xl md:text-6xl font-bold mb-6">Empowering Education</h1>
                            <p class="text-xl md:text-2xl mb-8 max-w-2xl mx-auto">Supporting student success through fair and consistent disciplinary practices</p>
                            <a href="pages/Auth/signup.php" class="btn btn-primary px-8 py-3 text-lg font-semibold hover:bg-primary-dark transition-all transform hover:scale-105">
                                Join Us Today
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Carousel Controls -->
            <button id="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all z-10">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all z-10">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <!-- Carousel Indicators -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex space-x-2 z-10">
                <button class="carousel-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white transition-all" data-slide="0"></button>
                <button class="carousel-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white transition-all" data-slide="1"></button>
                <button class="carousel-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white transition-all" data-slide="2"></button>
            </div>
        </div>
        
        <!-- Scroll Down Indicator -->
        <a href="#features" class="absolute bottom-8 left-1/2 -translate-x-1/2 text-white animate-bounce z-10">
            <i class="fas fa-chevron-down text-2xl"></i>
        </a>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-primary mb-4">System Features</h2>
                <p class="text-gray max-w-2xl mx-auto">Discover how our disciplinary management system improves efficiency and transparency</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow text-center border-t-4 border-primary">
                    <div class="text-primary text-4xl mb-6">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3 class="text-xl font-bold text-primary mb-4">Centralized Case Records</h3>
                    <p class="text-gray">Track academic and behavioral violations in one secure place with easy access for authorized personnel.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow text-center border-t-4 border-primary">
                    <div class="text-primary text-4xl mb-6">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="text-xl font-bold text-primary mb-4">Faster Case Processing</h3>
                    <p class="text-gray">Submit, review, and resolve incidents without paperwork delays. Streamlined workflows save time.</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow text-center border-t-4 border-primary">
                    <div class="text-primary text-4xl mb-6">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="text-xl font-bold text-primary mb-4">Parent & Student Transparency</h3>
                    <p class="text-gray">Keep parents informed and allow students to follow their cases and appeals with full visibility.</p>
                </div>
                
                <!-- Feature 4 -->
                <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow text-center border-t-4 border-primary">
                    <div class="text-primary text-4xl mb-6">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="text-xl font-bold text-primary mb-4">Reports & Analytics</h3>
                    <p class="text-gray">Generate insights to improve policies and decision-making with comprehensive reporting tools.</p>
                </div>
                
                <!-- Feature 5 -->
                <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow text-center border-t-4 border-primary">
                    <div class="text-primary text-4xl mb-6">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold text-primary mb-4">Secure Access</h3>
                    <p class="text-gray">Role-based permissions ensure privacy and data protection with multiple security layers.</p>
                </div>
            </div>
        </div>
    </section>
    <section id="about" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-primary mb-4">About ZPPSU</h2>
            </div>
            
            <div class="max-w-4xl mx-auto text-center">
                <p class="text-lg text-gray mb-6">
                    Zamboanga Peninsula Polytechnic State University is committed to fairness and transparency in handling student disciplinary cases. This system was developed to ensure efficiency, accountability, and clear communication among departments, teachers, parents, and students.
                </p>
                <p class="text-lg text-gray">
                    Our mission is to create a safe and conducive learning environment where disciplinary matters are handled with professionalism, consistency, and respect for all parties involved.
                </p>
            </div>
        </div>
    </section>
    <section id="violations" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-primary mb-4">Violations & Penalties</h2>
                <p class="text-gray max-w-3xl mx-auto">Review the university's disciplinary policies and corresponding penalties for violations</p>
            </div>
            <div class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200 mb-12">
                <h3 class="text-xl font-bold text-primary mb-4">For Inquiries</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-primary text-xl mr-3"></i>
                        <div>
                            <h4 class="font-semibold">Email</h4>
                            <p class="text-gray-600">discipline@zppsu.edu.ph</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-phone text-primary text-xl mr-3"></i>
                        <div>
                            <h4 class="font-semibold">Phone</h4>
                            <p class="text-gray-600">(062) 991-2345</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt text-primary text-xl mt-1 mr-3"></i>
                        <div>
                            <h4 class="font-semibold">Location</h4>
                            <p class="text-gray-600">ZPPSU, Zamboanga City</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            // Get all categories first
            $db = new PDO('mysql:host=localhost;dbname=zppsu_disciplinary', 'root', '');
            $categories = $db->query("SELECT * FROM violation_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize an array to store all violations data
            $allViolationsData = [];
            
            // Pre-fetch all violations data for each category
            foreach ($categories as $category) {
                $tabId = 'cat' . $category['id'];
                
                // Get violations for this category with their penalties
                $violations = $db->prepare("
                    SELECT v.*, 
                           p.offense_number,
                           p.penalty_description,
                           p.suspension_days,
                           p.community_service_days,
                           p.is_expulsion
                    FROM violation_types v
                    LEFT JOIN violation_penalties p ON v.id = p.violation_type_id
                    WHERE v.category_id = ?
                    ORDER BY v.id, p.offense_number
                ");
                $violations->execute([$category['id']]);
                $violations = $violations->fetchAll(PDO::FETCH_ASSOC);
                
                // Organize violations with their penalties
                $organizedViolations = [];
                foreach ($violations as $row) {
                    $violationId = $row['id'];
                    
                    if (!isset($organizedViolations[$violationId])) {
                        $organizedViolations[$violationId] = [
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'description' => $row['description'],
                            'penalties' => []
                        ];
                    }
                    
                    if ($row['offense_number']) {
                        $organizedViolations[$violationId]['penalties'][$row['offense_number']] = [
                            'description' => $row['penalty_description'],
                            'suspension_days' => $row['suspension_days'],
                            'community_service_days' => $row['community_service_days'],
                            'is_expulsion' => $row['is_expulsion']
                        ];
                    }
                }
                
                $allViolationsData[$tabId] = [
                    'category' => $category,
                    'violations' => $organizedViolations
                ];
            }
            ?>
            
            <div x-data="{ activeTab: '<?= !empty($categories) ? 'cat' . $categories[0]['id'] : '' ?>' }">
                <!-- Tabs Navigation -->
                <div class="flex flex-wrap border-b border-gray-200 mb-6">
                    <?php foreach ($categories as $category): 
                        $tabId = 'cat' . $category['id'];
                    ?>
                    <button
                        @click="activeTab = '<?= $tabId ?>'"
                        :class="{ 'bg-primary text-white': activeTab === '<?= $tabId ?>', 'text-gray-600 hover:text-gray-800': activeTab !== '<?= $tabId ?>' }"
                        class="px-6 py-3 font-medium text-sm rounded-t-lg focus:outline-none transition-colors duration-200"
                        x-bind:aria-selected="activeTab === '<?= $tabId ?>'"
                    >
                        <?= htmlspecialchars($category['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Tabs Content -->
                <?php foreach ($allViolationsData as $tabId => $data): 
                    $category = $data['category'];
                    $organizedViolations = $data['violations'];
                ?>
                <div x-show="activeTab === '<?= $tabId ?>'" 
                     x-transition:enter="transition ease-out duration-300" 
                     x-transition:enter-start="opacity-0" 
                     x-transition:enter-end="opacity-100"
                     class="space-y-3">
                    <div class="text-sm text-gray-600">
                        Category: <span class="font-medium"><?= htmlspecialchars($category['name']) ?></span> 
                        <?php if (!empty($category['description'])): ?>
                        — <?= htmlspecialchars($category['description']) ?>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
                        <?php if (!empty($organizedViolations)): ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violation / Offense</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Offense</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Second Offense</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Third Offense</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($organizedViolations as $violation): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-dark">
                                        <div class="flex flex-col">
                                            <span><?= htmlspecialchars($violation['name']) ?></span>
                                            <span class="text-xs text-gray-500">
                                                <?= htmlspecialchars($violation['code']) ?>
                                                <?php if (!empty($violation['description'])): ?>
                                                — <?= htmlspecialchars($violation['description']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <?php for ($i = 1; $i <= 3; $i++): 
                                        $penalty = $violation['penalties'][$i] ?? [
                                            'description' => '—',
                                            'suspension_days' => 0,
                                            'community_service_days' => 0,
                                            'is_expulsion' => 0
                                        ];
                                        $suspension = $penalty['is_expulsion'] ? 'Expulsion' : 
                                                    ($penalty['suspension_days'] ? $penalty['suspension_days'] . ' days suspension' : 'None');
                                        $service = $penalty['community_service_days'] ? $penalty['community_service_days'] . ' days community service' : '';
                                    ?>
                                    <td class="px-4 py-3 text-sm align-top">
                                        <div class="font-medium"><?= htmlspecialchars($penalty['description']) ?></div>
                                        <?php if ($penalty['description'] !== '—'): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <div><?= $suspension ?></div>
                                            <?php if (!empty($service)): ?>
                                            <div><?= $service ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="px-4 py-6 text-center text-gray-500">No violations found in this category.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-primary text-white py-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between mb-8 gap-8">
                <!-- Logo and Description -->
                <div class="md:w-1/3">
                    <div class="text-white font-bold text-xl mb-4">ZPPSU Student Disciplinary Management System</div>
                    <p class="text-gray-200">Ensuring transparency and efficiency in student disciplinary processes at ZPPSU.</p>
                </div>
                <div class="md:w-1/4">
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-gray-200 hover:text-white transition-colors">Home</a></li>
                        <li><a href="#features" class="text-gray-200 hover:text-white transition-colors">Features</a></li>
                        <li><a href="#about" class="text-gray-200 hover:text-white transition-colors">About</a></li>
                        <li><a href="#contact" class="text-gray-200 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <!-- Legal Links -->
                <div class="md:w-1/4">
                    <h4 class="text-lg font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-200 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-200 hover:text-white transition-colors">Terms of Use</a></li>
                        <li><a href="#" class="text-gray-200 hover:text-white transition-colors">Data Protection</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-gray-700 text-center text-gray-200">
                <p>&copy; 2025 ZPPSU Student Disciplinary Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="fixed bottom-8 right-8 bg-primary text-white p-3 rounded-full shadow-lg opacity-0 invisible transition-all duration-300 transform hover:scale-110 hover:bg-primary-dark z-50">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="src/js/main.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
        });

        // Mobile Menu Toggle
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
                mobileMenuButton.innerHTML = mobileMenu.classList.contains('hidden') 
                    ? '<i class="fas fa-bars"></i>' 
                    : '<i class="fas fa-times"></i>';
            });
        }

        // Smooth Scrolling for Anchor Links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                        mobileMenuButton.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                }
            });
        });

        // Scroll to Top Button
        const scrollToTopBtn = document.getElementById('scrollToTop');
        if (scrollToTopBtn) {
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    scrollToTopBtn.classList.remove('opacity-0', 'invisible');
                    scrollToTopBtn.classList.add('opacity-100', 'visible');
                } else {
                    scrollToTopBtn.classList.add('opacity-0', 'invisible');
                    scrollToTopBtn.classList.remove('opacity-100', 'visible');
                }
            });

            scrollToTopBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }

        // Carousel Functionality
        const carousel = document.querySelector('.carousel-track');
        const items = document.querySelectorAll('.carousel-item');
        const prevBtn = document.getElementById('prevSlide');
        const nextBtn = document.getElementById('nextSlide');
        const indicators = document.querySelectorAll('.carousel-indicator');
        
        let currentIndex = 0;
        const totalItems = items.length;
        
        function updateCarousel() {
            const itemWidth = 100; // 100%
            carousel.style.transform = `translateX(-${currentIndex * itemWidth}%)`;
            
            // Update indicators
            indicators.forEach((indicator, index) => {
                if (index === currentIndex) {
                    indicator.classList.add('!bg-white', 'w-6');
                    indicator.classList.remove('bg-white/50');
                } else {
                    indicator.classList.remove('!bg-white', 'w-6');
                    indicator.classList.add('bg-white/50');
                }
            });
        }
        
        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }
        
        function prevSlide() {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        }
        
        // Auto-advance carousel
        let carouselInterval = setInterval(nextSlide, 5000);
        
        // Pause auto-advance on hover
        const carouselContainer = document.getElementById('heroCarousel');
        if (carouselContainer) {
            carouselContainer.addEventListener('mouseenter', () => {
                clearInterval(carouselInterval);
            });
            
            carouselContainer.addEventListener('mouseleave', () => {
                clearInterval(carouselInterval);
                carouselInterval = setInterval(nextSlide, 5000);
            });
        }
        
        // Event Listeners
        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', () => {
                prevSlide();
                clearInterval(carouselInterval);
                carouselInterval = setInterval(nextSlide, 5000);
            });
            
            nextBtn.addEventListener('click', () => {
                nextSlide();
                clearInterval(carouselInterval);
                carouselInterval = setInterval(nextSlide, 5000);
            });
        }
        
        // Indicator click events
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel();
                clearInterval(carouselInterval);
                carouselInterval = setInterval(nextSlide, 5000);
            });
        });
        
        // Initialize carousel
        updateCarousel();

        // Form Submission
        const contactForm = document.getElementById('supportForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic form validation
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const subject = document.getElementById('subject').value.trim();
                const message = document.getElementById('message').value.trim();
                
                if (!name || !email || !subject || !message) {
                    alert('Please fill in all fields');
                    return;
                }
                
                // Here you would typically send the form data to a server
                // For now, we'll just show a success message
                alert('Thank you for your message! We will get back to you soon.');
                contactForm.reset();
            });
        }
    </script>
</body>
</html>