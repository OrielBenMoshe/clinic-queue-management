#!/bin/bash
# 🧹 סקריפט ניקוי Cache לאחר עדכון גירסה 0.3.0
# שימוש: ./clear-cache.sh

echo "🧹 מנקה Cache עבור מערכת ניהול מרפאות v0.3.0..."
echo ""

# בדוק אם WP-CLI זמין
if command -v wp &> /dev/null; then
    echo "✅ WP-CLI נמצא - מנקה WordPress cache..."
    wp cache flush 2>/dev/null && echo "   ✓ WordPress cache נוקה" || echo "   ⚠ לא הצליח לנקות WordPress cache"
    
    # Flush rewrite rules
    echo "✅ מרענן rewrite rules..."
    wp rewrite flush 2>/dev/null && echo "   ✓ Rewrite rules רוענן" || echo "   ⚠ לא הצליח לרענן rewrite rules"
else
    echo "⚠ WP-CLI לא זמין - דלג על ניקוי WordPress cache"
    echo "   אפשר לנקות ידנית: Settings → Permalinks → Save Changes"
fi

echo ""

# בדוק Redis
if command -v redis-cli &> /dev/null; then
    echo "✅ Redis נמצא - מנקה cache..."
    redis-cli FLUSHALL &> /dev/null && echo "   ✓ Redis cache נוקה" || echo "   ⚠ לא הצליח לנקות Redis cache"
else
    echo "ℹ️  Redis לא מותקן או לא זמין"
fi

echo ""

# בדוק OPcache
echo "✅ מנסה לרענן OPcache..."
echo "   📝 אם אתה משתמש ב-PHP-FPM, הרץ:"
echo "      sudo systemctl restart php8.1-fpm"
echo "   או שתף את הקובץ flush-opcache.php (ראה CACHE_CLEAR_INSTRUCTIONS.md)"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ ניקוי Cache הושלם!"
echo ""
echo "📋 שלבים נוספים:"
echo "   1. נקה browser cache (Ctrl+Shift+R)"
echo "   2. אם יש CDN (Cloudflare), נקה גם שם"
echo "   3. בדוק ב-DevTools Console שאין שגיאות"
echo ""
echo "📚 למידע מפורט: CACHE_CLEAR_INSTRUCTIONS.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
