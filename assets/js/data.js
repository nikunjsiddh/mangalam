/* Mangalam Jewellers — catalogue, journal and testimonial data.
 * Edit products, articles and testimonials here; every page renders from this file.
 */
(function (root) {
  "use strict";

  // Campaign photography (assets/images/campaign/) and one image per category, used for the category
  // arches, chips and menu thumbnails. The mangalsutra and craft images are the earlier artwork: the
  // shoot has no mangalsutra or atelier photographs yet.
  var CAMPAIGN = "assets/images/campaign/";
  var IMG = {
    hero: CAMPAIGN + "hero.jpg",
    craft: "assets/images/mangalam-craft.jpg",
    rings: CAMPAIGN + "cat-rings.jpg",
    necklaces: CAMPAIGN + "cat-necklaces.jpg",
    earrings: CAMPAIGN + "cat-earrings.jpg",
    bangles: CAMPAIGN + "cat-bangles.jpg",
    bracelets: CAMPAIGN + "cat-bracelets.jpg",
    pendants: CAMPAIGN + "cat-pendants.jpg",
    mangalsutra: "assets/images/products/mangalsutra.jpg",
    bridal: CAMPAIGN + "cat-bridal.jpg",
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

  /* Photographs of each piece, from assets/images/products/: the first is its main image, the others
     fill the product page gallery and the second shows when a card is hovered. Every file has a 600 px
     "-sm" copy for cards and lists. Pieces without photographs (mangalsutra) use their category image. */
  var photos = {
    "Classic Solitaire Ring": ["ring-pearl-ruby", "hands-pink-silk"],
    "Heritage Gold Ring": ["ring-antique-gold", "necklace-polki-pearl"],
    "Diamond Halo Ring": ["ring-sage-polki", "ring-sage-polki-2"],
    "Rose Gold Band": ["ring-ruby-dome", "bangles-bridal-ivory"],
    "Elegant Everyday Ring": ["ring-pearl-dome", "bangles-kada-pair"],
    "Traditional Gujarati Ring": ["ring-kundan-pearl", "bangles-kada-pair"],
    "Statement Ring": ["ring-cocktail", "bangle-gold-cuff"],
    "Floral Diamond Ring": ["ring-kundan-floral", "hands-pink-silk"],
    "Royal Heritage Necklace": ["necklace-emerald-plaque", "necklace-emerald-plaque-2", "earring-emerald-plaque"],
    "Temple Gold Necklace": ["necklace-temple-gold", "necklace-temple-gold-2"],
    "Diamond Collar Necklace": ["necklace-diamond", "necklace-diamond-2", "earring-diamond"],
    "Bridal Necklace Set": ["necklace-polki-pearl", "necklace-polki-pearl-2", "necklace-polki-pearl-3"],
    "Antique Gold Necklace": ["necklace-antique-gold", "necklace-antique-gold-2"],
    "Gujarati Heritage Necklace": ["necklace-gold-collar", "necklace-gold-collar-2"],
    "Classic Jhumka": ["earring-jhumka", "bridal-rani-haar-2"],
    "Diamond Drop Earrings": ["earring-diamond", "necklace-diamond"],
    "Traditional Gujarati Earrings": ["earring-emerald-plaque", "earring-emerald-plaque-2"],
    "Gold Chandbali": ["earring-chandbali", "earring-chandbali-2", "bridal-emerald-choker-2"],
    "Contemporary Studs": ["earring-polki-studs", "bridal-polki-set"],
    "Bridal Jhumka": ["earring-gold-jhumka", "necklace-gold-collar"],
    "Heritage Gold Bangles": ["bangles-kada-pair", "bangles-kada-pair-2", "bangles-kada-pair-3"],
    "Diamond Kada": ["bangle-pave-cuff", "bangle-pave-cuff-2"],
    "Traditional Kada Set": ["bangles-filigree-kada"],
    "Antique Bangles": ["bangle-gold-cuff", "ring-cocktail"],
    "Bridal Bangle Set": ["bangles-bridal-ivory", "ring-ruby-dome"],
    "Diamond Tennis Bracelet": ["bracelet-rose-diamond-2", "bracelet-rose-diamond", "bracelet-rose-diamond-3"],
    "Gold Chain Bracelet": ["bracelet-gold-bar", "bracelet-gold-bar-2"],
    "Elegant Daily Bracelet": ["bracelet-pearl-kundan", "bracelet-pearl-kundan-2"],
    "Charm Bracelet": ["bracelet-meenakari", "hands-pink-silk"],
    "Statement Bracelet": ["bracelet-polki-emerald", "bracelet-polki-emerald-2", "bracelet-polki-emerald-3"],
    "Classic Gold Pendant": ["pendant-medallion", "bridal-rani-haar"],
    "Diamond Solitaire Pendant": ["pendant-peacock-2", "pendant-peacock"],
    "Heritage Pendant": ["pendant-peacock", "pendant-peacock-2"],
    "Minimal Gold Pendant": ["pendant-tassel", "pendant-tassel-2"],
    "Rajwadi Bridal Necklace Set": ["bridal-polki-set", "bridal-polki-set-2", "earring-polki-studs"],
    "Maharani Bridal Jhumka": ["earring-chandelier", "earring-chandelier-2"],
    "Heritage Bridal Bangles": ["bangles-kundan-kada", "bridal-nath-2"],
    "Saat Phere Bridal Set": ["bridal-lehenga", "bridal-lehenga-2"],
    "Polki Bridal Choker": ["bridal-emerald-choker", "bridal-emerald-choker-2", "earring-chandbali"],
    "Gujarati Rani Haar": ["bridal-rani-haar", "bridal-rani-haar-2", "bridal-rani-haar-3"],
    "Bridal Maang Tikka": ["bridal-tikka", "bridal-tikka-2", "bridal-tikka-3"],
    "Royal Bridal Nath": ["bridal-nath", "bridal-nath-2"],
  };
  var small = function (src) { return src.replace(/\.jpg$/, "-sm.jpg"); };

  var positions = ["center", "35% 50%", "65% 45%", "50% 60%", "25% 60%", "75% 55%", "40% 35%", "60% 70%"];

  function slugify(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
  }

  var products = [];
  categories.forEach(function (category, categoryIndex) {
    names[category].forEach(function (name, index) {
      var metal = /diamond|solitaire|halo|tennis|floral/i.test(name) ? "Diamond" : "Gold";
      var gallery = (photos[name] || []).map(function (file) { return "assets/images/products/" + file + ".jpg"; });
      products.push({
        slug: slugify(name),
        name: name,
        category: category,
        price: 18500 + categoryIndex * 9500 + index * 13750,
        metal: metal,
        style: /bridal/i.test(name) ? "Bridal"
          : /heritage|traditional|temple|antique|rajwadi|maharani|gujarati|jhumka|chandbali|rani/i.test(name) ? "Traditional"
          : /modern|contemporary|minimal|daily|everyday/i.test(name) ? "Modern"
          : "Classic",
        image: gallery[0] || IMG[category],
        thumb: gallery.length ? small(gallery[0]) : IMG[category],
        gallery: gallery,
        imagePosition: gallery.length ? "center" : positions[index] || "center",
        description: "An enduring " + name.toLowerCase() + ", composed with meticulous detail by Mangalam's master karigars for celebrations today and generations to come.",
        purity: metal === "Diamond" || /rose/i.test(name) ? "18K" : "22K", // diamond settings need 18K's strength
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

  var featuredSlugs = ["royal-heritage-necklace", "classic-jhumka", "statement-ring", "heritage-gold-bangles", "saat-phere-bridal-set", "diamond-tennis-bracelet", "rajwadi-bridal-necklace-set", "diamond-halo-ring"];

  /* ---------- Journal ---------- */
  var articles = [
    {
      slug: "art-of-bridal-jewellery", title: "The Art of Bridal Jewellery", category: "Bridal", readTime: "6 min read", date: "September 2026",
      excerpt: "A bridal set is never one jewel — it is a composition. Here is how our karigars balance heirloom weight with modern grace.",
      image: CAMPAIGN + "article-bridal.jpg", imagePosition: "center",
      body: [
        "Every Mangalam bridal commission begins with a conversation — about the wedding, the silks, the moments the bride will treasure. Only then do our designers begin sketching, composing necklaces, jhumkas, bangles and tikka as one harmonious whole.",
        "The craft itself follows generations of Gujarati technique: hand-drawn gold wire, stone-by-stone setting, and a final polish that gives every surface its quiet glow. A single bridal set can pass through more than two hundred hours of skilled hands.",
        "Our advice to every bride: choose one hero piece that feels unmistakably yours, and let the rest of the set support it. Trends will come and go — the jewel you feel beautiful in is the one you will keep forever.",
      ],
    },
    {
      slug: "understanding-gold-purity", title: "Understanding Gold Purity", category: "Guide", readTime: "4 min read", date: "August 2026",
      excerpt: "22K or 18K? Karats, hallmarks and what they truly mean for the jewellery you wear every day.",
      image: CAMPAIGN + "article-gold.jpg", imagePosition: "center",
      body: [
        "Gold purity is measured in karats. Pure gold is 24K — too soft for everyday wear — so it is blended with alloys to create 22K, beloved in Gujarati households for its rich warmth, and 18K, favoured for diamond-set designs that need extra strength.",
        "Every Mangalam piece carries a BIS hallmark, your assurance of the exact purity stamped on it. We recommend 22K for traditional pieces and heirloom bangles, and 18K for rings, bracelets and modern silhouettes worn daily.",
        "When in doubt, ask us. Understanding purity is not technical — it is simply the difference between jewellery you admire and jewellery you trust.",
      ],
    },
    {
      slug: "gujarati-jewellery-heritage", title: "Gujarati Jewellery Heritage", category: "Heritage", readTime: "7 min read", date: "August 2026",
      excerpt: "From patlas to pansets — the motifs, rituals and artistry that shape every Mangalam creation.",
      image: CAMPAIGN + "article-heritage.jpg", imagePosition: "center",
      body: [
        "Gujarat's jewellery tradition is one of India's oldest living crafts. Every region carries its own signature — the layered rani haar of Saurashtra, the geometric kundan of Kutch, the temple-inspired gold of the southern plains.",
        "Our karigars grew up with these forms. The paisley, the peacock, the flowering vine — these are not printed decorations but hand-carved memories, passed from master to apprentice across generations.",
        "At Mangalam, heritage is not preserved behind glass. It is worn to weddings, gifted at births, and reimagined each season — so that tradition continues to live, glitter and evolve.",
      ],
    },
    {
      slug: "choosing-your-signature-piece", title: "Choosing Your Signature Piece", category: "Style", readTime: "5 min read", date: "July 2026",
      excerpt: "One jewel that feels like you. Here's how to find it — and why it matters more than a full collection.",
      image: CAMPAIGN + "article-signature.jpg", imagePosition: "center",
      body: [
        "Most women own many jewels but wear only a few. The pieces we reach for share something: they suit our skin, our routine and our personality without effort. That is a signature piece.",
        "Start with honesty about your life — do you type all day, drape saris on weekends, dress up once a season? A sleek diamond band may serve you better than a grand polki choker, or quite the opposite.",
        "Visit us for a private consultation and we will help you try pieces slowly, in good light, without pressure. The right jewel announces itself the moment you stop thinking about it.",
      ],
    },
    {
      slug: "jewellery-care-guide", title: "Jewellery Care Guide", category: "Guide", readTime: "4 min read", date: "July 2026",
      excerpt: "Simple rituals to keep gold glowing and diamonds brilliant for the decades to come.",
      image: CAMPAIGN + "article-care.jpg", imagePosition: "center",
      body: [
        "Gold asks for little: keep it away from perfume and chlorine, wipe it gently with a soft cloth after wear, and store each piece separately so surfaces never scratch each other.",
        "Diamonds are the hardest natural material but love collecting everyday film. A soak in warm water with a drop of mild soap, a gentle brush, and a pat dry restores their fire in minutes.",
        "Bring your Mangalam pieces home to us once a year. Our karigars will clean, inspect settings and re-polish them — a small service that keeps heirlooms ready for the next generation.",
      ],
    },
    {
      slug: "modern-bridal-trends", title: "Modern Bridal Trends", category: "Bridal", readTime: "5 min read", date: "June 2026",
      excerpt: "Lighter sets, convertible necklaces and detachable haars — how today's brides are reimagining tradition.",
      image: CAMPAIGN + "article-trends.jpg", imagePosition: "center",
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
    small: small,
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
