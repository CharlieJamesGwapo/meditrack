// Check if user is logged in
async function checkAuth() {
    try {
        const response = await fetch('../api/auth/check-session.php');
        const data = await response.json();
        
        if (!data.logged_in) {
            window.location.href = 'login.html';
            return null;
        }
        
        return data.user;
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
        return null;
    }
}
