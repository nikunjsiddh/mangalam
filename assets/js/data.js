/* Mangalam Jewellers — catalogue, journal and testimonial data.
 * Edit products, articles and testimonials here; every page renders from this file.
 */
(function (root) {
  "use strict";

  var IMG = {
    hero: "assets/images/mangalam-hero.jpg",
    craft: "assets/images/mangalam-craft.jpg",
    rings: "assets/images/products/rings.jpg",
    necklaces: "assets/images/products/necklaces.jpg",
    earrings: "assets/images/products/earrings.jpg",
    bangles: "assets/images/products/bangles.jpg",
    bracelets: "assets/images/products/bracelets.jpg",
    pendants: "assets/images/products/pendants.jpg",
    mangalsutra: "assets/images/products/mangalsutra.jpg",
    bridal: "assets/images/products/bridal.jpg",
  };

  /* ---------- Catalogue ---------- */
  var categories = ["rings", "necklaces", "earrings", "bangles", "bracelets", "pendants", "mangalsutra", "bridal"];

  var names = {
    rings: ["Classic Solitaire Ring", "Heritage Gold Ring", "Diamond Halo Ring", "Rose Gold Band", "Elegant Everyday Ring", "Traditional Gujarati Ring", "Statement Ring", "Floral Diamond Ring"],
    necklaces: ["Royal Heritage Necklace", "Temple Gold Necklace", "Diamond Collar Necklace", "Bridal Necklace Set", "Antique Gold Necklace", "Gujarati Heritage Necklace"],
    earrings: ["Classic Jhumka", "Diamond Drop Earrings", "Traditional Gujarati Earrings", "Gold Chandbali", "Contemporary Studs", "Bridal Jhumka"],
    bangles: ["Heritage Gold Bangles", "Diamond Kada", "Traditional Kada Set", "Antique Bangles", "Bridal Bangle Set"],
    bracelets: ["Diamond Tennis Bracelet", "Gold Chain Bracelet", "Elegant Daily Bracelet", "Charm Bracelet", "Statement Bracelet"],
    pendants: ["Classic Gold Pendant", "Diamond Solitaire Pendant", "Heritage Pendant", "Minimal Gold Pendant"],
    mangalsutra: ["Classic Mangalsutra", "Diamond Mangalsutra", "Modern Mangalsutra", "Traditional Gold Mangalsutra"],
    bridal: ["Rajwadi Bridal Necklace Set", "Maharani Bridal Jhumka", "Heritage Bridal Bangles", "Saat Phere Bridal Set", "Polki Bridal Choker", "Gujarati Rani Haar", "Bridal Maang Tikka", "Royal Bridal Nath"],
  };

  var positions = ["center", "35% 50%", "65% 45%", "50% 60%", "25% 60%", "75% 55%", "40% 35%", "60% 70%"];

  function slugify(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
  }

  var products = [];
  categories.forEach(function (category, categoryIndex) {
    names[category].forEach(function (name, index) {
      products.push({
        slug: slugify(name),
        name: name,
        category: category,
        price: 18500 + categoryIndex * 9500 + index * 13750,
        metal: /diamond|solitaire|halo|tennis|floral/i.test(name) ? "Diamond" : "Gold",
        style: /bridal/i.test(name) ? "Bridal"
          : /heritage|traditional|temple|antique|rajwadi|maharani|gujarati|jhumka|chandbali|rani/i.test(name) ? "Traditional"
          : /modern|contemporary|minimal|daily|everyday/i.test(name) ? "Modern"
          : "Classic",
        image: IMG[category],
        imagePosition: positions[index] || "center",
        description: "An enduring " + name.toLowerCase() + ", composed with meticulous detail by Mangalam's master karigars for celebrations today and generations to come.",
        purity: /diamond|rose/i.test(name) ? "18K" : "22K",
        collection: category === "bridal" ? "The Bridal Edit" : category === "mangalsutra" ? "Sacred Bonds" : "Mangalam Signature",
        isNew: index < 2,
      });
    });
  });

  var categoryCopy = {
    rings: "Discover refined rings crafted for everyday elegance and unforgettable moments.",
    necklaces: "Statement necklaces shaped by heritage, light and exceptional handwork.",
    earrings: "From classic jhumkas to modern diamonds, designed to frame every expression.",
    bangles: "Gold bangles and kadas that carry the rhythm of celebration.",
    bracelets: "Refined bracelets created for effortless, everyday distinction.",
    pendants: "Meaningful pendants, delicately crafted to stay close.",
    mangalsutra: "Sacred tradition interpreted with grace for the modern woman.",
    bridal: "A deeply personal curation of jewels for your most unforgettable chapter.",
  };

  var featuredSlugs = ["royal-heritage-necklace", "classic-jhumka", "classic-solitaire-ring", "heritage-gold-bangles", "diamond-mangalsutra", "diamond-tennis-bracelet", "rajwadi-bridal-necklace-set", "diamond-halo-ring"];

  /* ---------- Journal ---------- */
  var articles = [
    {
      slug: "art-of-bridal-jewellery", title: "The Art of Bridal Jewellery", category: "Bridal", readTime: "6 min read", date: "September 2026",
      excerpt: "A bridal set is never one jewel — it is a composition. Here is how our karigars balance heirloom weight with modern grace.",
      image: IMG.bridal, imagePosition: "center",
      body: [
        "Every Mangalam bridal commission begins with a conversation — about the wedding, the silks, the moments the bride will treasure. Only then do our designers begin sketching, composing necklaces, jhumkas, bangles and tikka as one harmonious whole.",
        "The craft itself follows generations of Gujarati technique: hand-drawn gold wire, stone-by-stone setting, and a final polish that gives every surface its quiet glow. A single bridal set can pass through more than two hundred hours of skilled hands.",
        "Our advice to every bride: choose one hero piece that feels unmistakably yours, and let the rest of the set support it. Trends will come and go — the jewel you feel beautiful in is the one you will keep forever.",
      ],
    },
    {
      slug: "understanding-gold-purity", title: "Understanding Gold Purity", category: "Guide", readTime: "4 min read", date: "August 2026",
      excerpt: "22K or 18K? Karats, hallmarks and what they truly mean for the jewellery you wear every day.",
      image: IMG.necklaces, imagePosition: "center",
      body: [
        "Gold purity is measured in karats. Pure gold is 24K — too soft for everyday wear — so it is blended with alloys to create 22K, beloved in Gujarati households for its rich warmth, and 18K, favoured for diamond-set designs that need extra strength.",
        "Every Mangalam piece carries a BIS hallmark, your assurance of the exact purity stamped on it. We recommend 22K for traditional pieces and heirloom bangles, and 18K for rings, bracelets and modern silhouettes worn daily.",
        "When in doubt, ask us. Understanding purity is not technical — it is simply the difference between jewellery you admire and jewellery you trust.",
      ],
    },
    {
      slug: "gujarati-jewellery-heritage", title: "Gujarati Jewellery Heritage", category: "Heritage", readTime: "7 min read", date: "August 2026",
      excerpt: "From patlas to pansets — the motifs, rituals and artistry that shape every Mangalam creation.",
      image: IMG.craft, imagePosition: "center",
      body: [
        "Gujarat's jewellery tradition is one of India's oldest living crafts. Every region carries its own signature — the layered rani haar of Saurashtra, the geometric kundan of Kutch, the temple-inspired gold of the southern plains.",
        "Our karigars grew up with these forms. The paisley, the peacock, the flowering vine — these are not printed decorations but hand-carved memories, passed from master to apprentice across generations.",
        "At Mangalam, heritage is not preserved behind glass. It is worn to weddings, gifted at births, and reimagined each season — so that tradition continues to live, glitter and evolve.",
      ],
    },
    {
      slug: "choosing-your-signature-piece", title: "Choosing Your Signature Piece", category: "Style", readTime: "5 min read", date: "July 2026",
      excerpt: "One jewel that feels like you. Here's how to find it — and why it matters more than a full collection.",
      image: IMG.hero, imagePosition: "center",
      body: [
        "Most women own many jewels but wear only a few. The pieces we reach for share something: they suit our skin, our routine and our personality without effort. That is a signature piece.",
        "Start with honesty about your life — do you type all day, drape saris on weekends, dress up once a season? A sleek diamond band may serve you better than a grand polki choker, or quite the opposite.",
        "Visit us for a private consultation and we will help you try pieces slowly, in good light, without pressure. The right jewel announces itself the moment you stop thinking about it.",
      ],
    },
    {
      slug: "jewellery-care-guide", title: "Jewellery Care Guide", category: "Guide", readTime: "4 min read", date: "July 2026",
      excerpt: "Simple rituals to keep gold glowing and diamonds brilliant for the decades to come.",
      image: IMG.necklaces, imagePosition: "50% 60%",
      body: [
        "Gold asks for little: keep it away from perfume and chlorine, wipe it gently with a soft cloth after wear, and store each piece separately so surfaces never scratch each other.",
        "Diamonds are the hardest natural material but love collecting everyday film. A soak in warm water with a drop of mild soap, a gentle brush, and a pat dry restores their fire in minutes.",
        "Bring your Mangalam pieces home to us once a year. Our karigars will clean, inspect settings and re-polish them — a small service that keeps heirlooms ready for the next generation.",
      ],
    },
    {
      slug: "modern-bridal-trends", title: "Modern Bridal Trends", category: "Bridal", readTime: "5 min read", date: "June 2026",
      excerpt: "Lighter sets, convertible necklaces and detachable haars — how today's brides are reimagining tradition.",
      image: IMG.bridal, imagePosition: "50% 55%",
      body: [
        "Today's bride wears her jewellery once, then lives in it. That is why convertible design — a choker that becomes a pendant, jhumkas with detachable drops — has become the most requested idea at our atelier.",
        "Weight is moving down and meaning is moving up: brides are choosing fewer, finer pieces with personal motifs — initials in Gujarati script, birthstones beside diamonds, heirloom stones reset into new settings.",
        "Tradition is not being replaced; it is being personalised. A Mangalam bridal set today honours the past and fits the future — exactly as it should.",
      ],
    },
  ];

  /* ---------- Testimonials ---------- */
  var testimonials = [
    { quote: "My bridal set felt like it had been in our family for generations — every guest asked about it, and I will treasure it forever.", who: "Aanya Desai", occasion: "Wedding, Surat" },
    { quote: "The mangalsutra Mangalam designed for us is exactly what I hoped for — tradition honoured, but unmistakably mine.", who: "Priya Patel", occasion: "Anniversary, Ahmedabad" },
    { quote: "Three generations of my family buy from Mangalam. The gold, the honesty, the warmth — nothing has changed.", who: "Meera Shah", occasion: "Loyal customer, Vadodara" },
  ];

  root.MJ = {
    IMG: IMG,
    categories: categories,
    products: products,
    categoryCopy: categoryCopy,
    featuredSlugs: featuredSlugs,
    articles: articles,
    testimonials: testimonials,
    capitalize: function (v) { return v.charAt(0).toUpperCase() + v.slice(1); },
    formatPrice: function (price) { return "₹ " + price.toLocaleString("en-IN"); },
  };
})(typeof window !== "undefined" ? window : globalThis);
