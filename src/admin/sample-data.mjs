/* Sample content for the admin design: customer enquiries, bookings, subscribers, the team and the
 * dashboard's figures. None of it is real — it only shows how those screens look when they are full.
 * Once the backend is connected it comes from the database. Everything else in the admin (products,
 * categories, pages, journal, offer, settings) already reads the website's own data. */

export const team = [
  { name: "Rohan Mehta", email: "rohan@mangalamjewellers.in", role: "Owner", status: "active", last: "Online now" },
  { name: "Kavya Shah", email: "kavya@mangalamjewellers.in", role: "Manager", status: "active", last: "2 hours ago" },
  { name: "Dhruv Patel", email: "dhruv@mangalamjewellers.in", role: "Editor", status: "active", last: "Yesterday" },
  { name: "Isha Vyas", email: "isha@mangalamjewellers.in", role: "Sales", status: "active", last: "3 days ago" },
  { name: "Nirav Joshi", email: "nirav@mangalamjewellers.in", role: "Sales", status: "invited", last: "Invitation sent 2 days ago" },
];

export const roles = [
  { name: "Owner", desc: "Everything, including settings and the team" },
  { name: "Manager", desc: "Catalogue, website content, customers and offers" },
  { name: "Editor", desc: "Catalogue and website content" },
  { name: "Sales", desc: "Enquiries and appointments" },
];

// [permission, allowed for Owner, Manager, Editor, Sales]
export const permissions = [
  ["See the dashboard", 1, 1, 1, 1],
  ["Add and edit products", 1, 1, 1, 0],
  ["Delete products", 1, 1, 0, 0],
  ["Edit the homepage, pages and journal", 1, 1, 1, 0],
  ["Upload and delete media", 1, 1, 1, 0],
  ["Reply to enquiries", 1, 1, 0, 1],
  ["Book and change appointments", 1, 1, 0, 1],
  ["Export subscribers", 1, 1, 0, 0],
  ["Edit the offer and announcements", 1, 1, 0, 0],
  ["Change settings", 1, 0, 0, 0],
  ["Invite and remove team members", 1, 0, 0, 0],
];

// source: "product" (the Enquire button on a product page) or "contact" (the contact page form, with its topic)
export const enquiries = [
  { name: "Ananya Parekh", email: "ananya.parekh@example.com", phone: "+91 98765 43210", source: "product", product: "royal-heritage-necklace", status: "new", ago: "12 min", when: "Today, 10:42 AM",
    message: "Hello, I love the Royal Heritage Necklace. Is it available in a slightly shorter length? I would also like to see it with the matching earrings before my sister's wedding in December." },
  { name: "Hetal Desai", email: "hetal.desai@example.com", phone: "+91 98765 43211", source: "contact", topic: "Bridal consultation", status: "new", ago: "1 hr", when: "Today, 9:58 AM",
    message: "We are planning a wedding in February and would like a private bridal consultation for the full set — necklace, jhumkas, bangles and tikka. Are weekend slots available in October?" },
  { name: "Kunal Shah", email: "kunal.shah@example.com", phone: "+91 98765 43212", source: "product", product: "diamond-tennis-bracelet", status: "new", ago: "3 hrs", when: "Today, 7:31 AM",
    message: "Could you share the diamond weight and clarity for this bracelet? I am looking for an anniversary gift and would like it engraved on the clasp if possible." },
  { name: "Meera Iyer", email: "meera.iyer@example.com", phone: "+91 98765 43213", source: "contact", topic: "Bespoke commission", status: "replied", ago: "Yesterday", when: "Yesterday, 6:15 PM",
    message: "I have my grandmother's old kundan pieces. Could your karigars reset the stones into a lighter, modern choker? Happy to bring them in.",
    reply: { by: "Kavya Shah", when: "Yesterday, 7:02 PM", text: "Thank you, Meera — we would be honoured. Please bring the pieces to Mangalam House; our designer will sketch two options for you on the day." } },
  { name: "Riya Trivedi", email: "riya.trivedi@example.com", phone: "+91 98765 43214", source: "product", product: "maharani-bridal-jhumka", status: "replied", ago: "Yesterday", when: "Yesterday, 1:20 PM",
    message: "Are the Maharani jhumkas heavy? I would like to wear them through a full day of ceremonies.",
    reply: { by: "Isha Vyas", when: "Yesterday, 3:45 PM", text: "Hi Riya, each earring weighs about 18 g and has a support chain, so they stay comfortable all day. Would you like to try them in store?" } },
  { name: "Aarav Kapadia", email: "aarav.kapadia@example.com", phone: "+91 98765 43215", source: "contact", topic: "Care & repairs", status: "replied", ago: "2 days", when: "Wed, 11:05 AM",
    message: "One of the hooks on my kada has loosened. Do you repair pieces bought five years ago?",
    reply: { by: "Isha Vyas", when: "Wed, 12:30 PM", text: "Of course — repairs on Mangalam pieces are free for life. Drop it in any day between 10:30 and 8:30." } },
  { name: "Nisha Mehta", email: "nisha.mehta@example.com", phone: "+91 98765 43216", source: "product", product: "classic-jhumka", status: "closed", ago: "3 days", when: "Tue, 4:40 PM",
    message: "Do these jhumkas come with screw backs? Buying for my mother." },
  { name: "Tanvi Rao", email: "tanvi.rao@example.com", phone: "+91 98765 43217", source: "contact", topic: "General enquiry", status: "closed", ago: "5 days", when: "Sun, 12:10 PM",
    message: "Are you open on Sundays during Navratri? We would like to visit as a family." },
  { name: "Devang Bhatt", email: "devang.bhatt@example.com", phone: "+91 98765 43218", source: "product", product: "diamond-halo-ring", status: "closed", ago: "1 week", when: "19 Sep, 5:25 PM",
    message: "Can the halo ring be made in 18K rose gold? Ring size 14." },
];

// day: days from the build date (negative = already happened)
export const appointments = [
  { day: -3, time: "12:00 PM", name: "Sonal Shah", phone: "+91 98765 40001", interest: "Gold jewellery", status: "completed", consultant: "Isha Vyas" },
  { day: -1, time: "5:30 PM", name: "Vikram Desai", phone: "+91 98765 40002", interest: "Diamond jewellery", status: "cancelled", consultant: "Isha Vyas" },
  { day: 0, time: "11:30 AM", name: "Hetal Desai", phone: "+91 98765 43211", interest: "Bridal jewellery", status: "confirmed", consultant: "Kavya Shah", notes: "Full bridal set; wedding in February. Bring the Rajwadi and Polki sets." },
  { day: 0, time: "4:00 PM", name: "Meera Iyer", phone: "+91 98765 43213", interest: "Bespoke commission", status: "confirmed", consultant: "Kavya Shah", notes: "Bringing her grandmother's kundan stones to reset." },
  { day: 1, time: "12:30 PM", name: "Priyanka Jain", phone: "+91 98765 40003", interest: "Bridal jewellery", status: "pending", consultant: "Isha Vyas" },
  { day: 2, time: "6:00 PM", name: "Kunal Shah", phone: "+91 98765 43212", interest: "Diamond jewellery", status: "confirmed", consultant: "Isha Vyas", notes: "Anniversary gift — tennis bracelet, engraving." },
  { day: 4, time: "11:00 AM", name: "Riya Trivedi", phone: "+91 98765 43214", interest: "Bridal jewellery", status: "confirmed", consultant: "Kavya Shah" },
  { day: 6, time: "3:30 PM", name: "Aditi Kulkarni", phone: "+91 98765 40004", interest: "Gold jewellery", status: "pending", consultant: "Isha Vyas" },
  { day: 9, time: "1:00 PM", name: "Farhan Merchant", phone: "+91 98765 40005", interest: "Bespoke commission", status: "confirmed", consultant: "Kavya Shah" },
  { day: 13, time: "12:00 PM", name: "Pooja Nair", phone: "+91 98765 40006", interest: "Bridal jewellery", status: "pending", consultant: "Kavya Shah" },
];

// ago: days before the build date
export const subscribers = [
  ["kavya.joshi@example.com", "Website footer", 0], ["ritu.agarwal@example.com", "Offer popup", 0], ["mansi.patel@example.com", "Website footer", 1],
  ["sneha.kothari@example.com", "In store", 2], ["pallavi.shah@example.com", "Offer popup", 3], ["amit.vora@example.com", "Website footer", 4],
  ["jinal.mehta@example.com", "Offer popup", 5], ["bhavna.desai@example.com", "In store", 6], ["rahul.trivedi@example.com", "Website footer", 8, "unsubscribed"],
  ["khushi.pandya@example.com", "Offer popup", 9], ["neha.bhavsar@example.com", "Website footer", 11], ["yash.modi@example.com", "In store", 12],
  ["disha.parikh@example.com", "Offer popup", 14], ["forum.sheth@example.com", "Website footer", 15], ["gauri.naik@example.com", "Website footer", 17],
  ["hiral.soni@example.com", "Offer popup", 19, "unsubscribed"], ["ishita.chokshi@example.com", "In store", 21], ["janvi.raval@example.com", "Website footer", 24],
  ["kinjal.dave@example.com", "Offer popup", 26], ["lopa.thakkar@example.com", "Website footer", 29], ["mitali.bhatt@example.com", "In store", 33],
  ["nupur.shukla@example.com", "Offer popup", 36], ["ojas.rana@example.com", "Website footer", 41, "unsubscribed"], ["parul.gandhi@example.com", "Website footer", 45],
];

export const figures = {
  // enquiries received per week, oldest first; the last value is this week
  weeklyEnquiries: [9, 12, 10, 14, 13, 17, 15, 19, 16, 22, 20, 24],
  // subscribers at the end of each of the last 12 months
  subscriberGrowth: [742, 781, 820, 874, 915, 968, 1010, 1065, 1109, 1172, 1226, 1284],
  subscribersThisMonth: 58,
};

export const notifications = [
  { icon: "inbox", text: "New enquiry from Ananya Parekh about the Royal Heritage Necklace", when: "12 min ago", href: "enquiries.html", unread: true },
  { icon: "calendar-check", text: "Hetal Desai confirmed her bridal consultation for today, 11:30 AM", when: "1 hr ago", href: "appointments.html", unread: true },
  { icon: "inbox", text: "New enquiry from Kunal Shah about the Diamond Tennis Bracelet", when: "3 hrs ago", href: "enquiries.html", unread: true },
  { icon: "image", text: "4 mangalsutra pieces are still waiting for photographs", when: "Yesterday", href: "products.html?flags=photos" },
  { icon: "mail", text: "58 people subscribed to the newsletter this month", when: "2 days ago", href: "subscribers.html" },
];
