<?php require_once __DIR__.'/includes/bootstrap.php';if(current_user())redirect('dashboard.php');page_header('Welcome');?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">A BETTER WAY TO MANAGE YOUR LIBRARY</p>
        <h1>Discover more.<br><span>Read further.</span></h1>
        <p>Find books, request a loan, and keep track of your library activity in one simple place.</p>
        <div class="hero-actions">
            <a class="btn" href="<?=e(app_url('register.php'))?>">Create an account</a>
            <a class="btn secondary" href="<?=e(app_url('login.php'))?>">Sign in</a>
        </div>
        <div class="hero-note">
            <span>Search the collection</span>
            <span>Manage your borrowing</span>
            <span>Stay up to date</span>
        </div>
    </div>
    <figure class="hero-photo">
        <img
            src="<?=e(app_url('assets/library-interior.jpg'))?>"
            alt="A welcoming library interior lined with shelves of books"
            fetchpriority="high"
        >
        <figcaption>
            <span>A quiet place to find your next favorite book.</span>
            <a href="https://unsplash.com" target="_blank" rel="noopener noreferrer">Photo: Unsplash</a>
        </figcaption>
    </figure>
</section>
<?php page_footer();?>