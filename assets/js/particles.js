// Basit particle efekti
document.addEventListener('DOMContentLoaded', function() {
    const particles = document.querySelector('.particles');
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.style.cssText = `
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(108, 92, 231, 0.2);
            border-radius: 50%;
            top: ${Math.random() * 100}%;
            left: ${Math.random() * 100}%;
            animation: float ${5 + Math.random() * 10}s linear infinite;
        `;
        particles.appendChild(particle);
    }
});