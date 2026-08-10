import {
  iceCreamCup,
  biscuitIceCream,
  familyIceCream,
  refrigerator,
  iceCream,
  milkshake,
  iceCreamKunafa,
  luqaimat,
  moltenCake,
  pancake,
  waffle,
  crepe,
  glassyPizza,
  coldDrinks,
  naturalJuices,
  hotDrinks,
  corn,
  browniesCake,
  mochi,
  sanSebastian,
  chocolateIce,
  vanillaaIce,
  strawberryIce,
  caramelIce,
  nescafeIce,
  coconutIce,
  mangoIce,
  bananaIce,
  grapeIce,
  bazookaIce,
  marioIce,
  lemonIce,
  arabianIce,
  nutellaIce,
  oreoIce,
  kitKatIce,
  floraIce,
  kinderBuenoIce,
  lotusIce,
  pistachioIce,
  blueberryIce,
  energyIce,
} from "../../../src/assets/images";
import type {
  IAddonOption,
  IBuilderProduct,
  IFlatListProduct,
  IFlavorOption,
  IMenuCategory,
  IProduct,
} from "../../../src/types/menu.types";

// ─── Categories (16, ported 1:1 from menuApiData.ts's FAKE_CATEGORIES +
// MenuClientPage.tsx's CATEGORY_ICONS/CATEGORY_GRADIENTS/CATEGORY_ACCENT) ────

export const FAKE_MENU_CATEGORIES: IMenuCategory[] = [
  { id: "ice-cream", label: "آيس كريم", icon: "ice-cream", accentColor: "#51c9f4", gradientFrom: "#51c9f4", gradientTo: "#388dab", sortOrder: 1 },
  { id: "brad", label: "براد", icon: "glass-water", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 2 },
  { id: "brad-boza", label: "براد مع بوظة", icon: "glass-water", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 3 },
  { id: "cold-drinks", label: "مشروبات باردة", icon: "cup-soda", accentColor: "#51b4f4", gradientFrom: "#51b4f4", gradientTo: "#2a6eb0", sortOrder: 4 },
  { id: "hot-drinks", label: "مشروبات ساخنة", icon: "cup-soda", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 5 },
  { id: "juices", label: "عصائر طبيعية", icon: "apple", accentColor: "#3fbd59", gradientFrom: "#3fbd59", gradientTo: "#2a8540", sortOrder: 6 },
  { id: "corn", label: "ذرة", icon: "apple", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 7 },
  { id: "milkshake", label: "ميلك شيك", icon: "milk", accentColor: "#f4519f", gradientFrom: "#f4519f", gradientTo: "#b02f74", sortOrder: 8 },
  { id: "kunafa", label: "كنافة آيس كريم", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 9 },
  { id: "loqaimat", label: "لقيمات", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 10 },
  { id: "pancake", label: "بان كيك", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 11 },
  { id: "waffle", label: "وافل", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 12 },
  { id: "crepe", label: "كريب", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 13 },
  { id: "pizza", label: "بيتزا جلاسيه", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 14 },
  { id: "molten", label: "مولتن كيك", icon: "cake", accentColor: "#f4a851", gradientFrom: "#f4a851", gradientTo: "#c97d2a", sortOrder: 15 },
  { id: "desserts", label: "حلويات", icon: "cake", accentColor: "#da51f4", gradientFrom: "#da51f4", gradientTo: "#9a2fb0", sortOrder: 16 },
];

// ─── Global flavor catalog (ported from OrderData.ts's CLASSIC_FLAVORS +
// SPECIAL_FLAVORS). Feeds the Cup/Family/Brad-boza flavor-ball picker. ─────

export const FAKE_FLAVORS: IFlavorOption[] = [
  { id: "chocolate", nameAr: "شوكولاتة", nameEn: "Chocolate", image: chocolateIce, family: "classic", available: true },
  { id: "vanilla", nameAr: "فانيلا", nameEn: "Vanilla", image: vanillaaIce, family: "classic", available: true },
  { id: "strawberry", nameAr: "فراولة", nameEn: "Strawberry", image: strawberryIce, family: "classic", available: true },
  { id: "caramel", nameAr: "كراميل", nameEn: "Caramel", image: caramelIce, family: "classic", available: true },
  { id: "dark-chocolate", nameAr: "شوكلاته مره", nameEn: "Dark Chocolate", image: chocolateIce, family: "classic", available: true },
  { id: "nescafe", nameAr: "نسكافيه", nameEn: "Nescafe", image: nescafeIce, family: "classic", available: true },
  { id: "coconut", nameAr: "جوز هند", nameEn: "Coconut", image: coconutIce, family: "classic", available: true },
  { id: "mango", nameAr: "مانجا", nameEn: "Mango", image: mangoIce, family: "classic", available: false },
  { id: "banana", nameAr: "موز", nameEn: "Banana", image: bananaIce, family: "classic", available: true },
  { id: "grape", nameAr: "عنب", nameEn: "Grape", image: grapeIce, family: "classic", available: true },
  { id: "bazooka", nameAr: "بازوكا", nameEn: "Bazooka", image: bazookaIce, family: "classic", available: true },
  { id: "mario", nameAr: "ماريو", nameEn: "Mario", image: marioIce, family: "classic", available: true },
  { id: "lemon", nameAr: "ليمون", nameEn: "Lemon", image: lemonIce, family: "classic", available: true },
  { id: "vanilla-stevia", nameAr: "فانيلا ستيفيا", nameEn: "Vanilla Stevia", image: vanillaaIce, family: "stevia", available: true },
  { id: "nescafe-stevia", nameAr: "نسكافيه ستيفيا", nameEn: "Nescafe Stevia", image: nescafeIce, family: "stevia", available: true },
  { id: "arabian", nameAr: "عربية", nameEn: "Arabian", image: arabianIce, family: "special", available: true },
  { id: "nutella", nameAr: "نوتيلا", nameEn: "Nutella", image: nutellaIce, family: "special", available: true },
  { id: "oreo", nameAr: "أوريو", nameEn: "Oreo", image: oreoIce, family: "special", available: true },
  { id: "kitkat", nameAr: "كت كات", nameEn: "KitKat", image: kitKatIce, family: "special", available: true },
  { id: "flora", nameAr: "فلوره", nameEn: "Flora", image: floraIce, family: "special", available: false },
  { id: "kinder", nameAr: "كندر", nameEn: "Kinder Bueno", image: kinderBuenoIce, family: "special", available: true },
  { id: "lotus", nameAr: "لوتس", nameEn: "Lotus", image: lotusIce, family: "special", available: true },
  { id: "pistachio", nameAr: "بيستاشيو", nameEn: "Pistachio", image: pistachioIce, family: "special", available: true, isPremiumMixFlavor: true },
];

// ─── Ice-cream builder products ────────────────────────────────────────────

const cup: IBuilderProduct = {
  id: "cup",
  slug: "cup",
  categoryId: "ice-cream",
  kind: "builder",
  name: "بوظة كاسة",
  description: "اختر الحاوية والحجم والنكهة المفضلة لديك",
  image: iceCreamCup,
  sortOrder: 1,
  available: true,
  hasNotes: true,
  containerOptions: [
    { id: "cup", label: "كاسة", available: true, name: "بوظة كاسة", image: iceCreamCup, pricingLabel: "الكاسة" },
    { id: "biscuit", label: "بسكوت", available: true, name: "بوظة بسكوت", image: biscuitIceCream, pricingLabel: "البسكوت" },
    { id: "takeaway", label: "تيك اواي", available: true, name: "بوظة تيك اواي", image: iceCreamCup, pricingLabel: "التيك اواي" },
  ],
  sizes: [
    { id: "cup-small", label: "صغير", maxBalls: 1, containerId: "cup", prices: [{ flavorFamily: "classic", price: 2 }, { flavorFamily: "special", price: 4 }] },
    { id: "cup-medium", label: "وسط", maxBalls: 2, containerId: "cup", prices: [{ flavorFamily: "classic", price: 3 }, { flavorFamily: "special", price: 5 }] },
    { id: "cup-large", label: "كبير", maxBalls: 3, containerId: "cup", prices: [{ flavorFamily: "classic", price: 5 }, { flavorFamily: "special", price: 7 }] },
    { id: "biscuit-small", label: "صغير", maxBalls: 1, containerId: "biscuit", prices: [{ flavorFamily: "classic", price: 2 }] },
    { id: "biscuit-medium", label: "وسط", maxBalls: 2, containerId: "biscuit", prices: [{ flavorFamily: "classic", price: 3 }, { flavorFamily: "special", price: 5 }] },
    { id: "biscuit-large", label: "كبير", maxBalls: 3, containerId: "biscuit", prices: [{ flavorFamily: "classic", price: 5 }, { flavorFamily: "special", price: 7 }] },
    { id: "takeaway-size", label: "تيك اواي", maxBalls: 3, containerId: "takeaway", prices: [{ flavorFamily: "classic", price: 5 }, { flavorFamily: "special", price: 7 }] },
  ],
  selectionMode: "repeatable",
  flavorFamilies: ["classic", "special", "mix"],
  hasExtraBiscuitAddon: true,
};

const family: IBuilderProduct = {
  id: "family",
  slug: "family",
  categoryId: "ice-cream",
  kind: "builder",
  name: "بوظة عائلي",
  description: "حصة عائلية كبيرة من البوظة اللذيذة",
  image: familyIceCream,
  sortOrder: 2,
  available: true,
  hasNotes: true,
  containerOptions: [
    { id: "classic-container", label: "بلاستيك", available: true, pricingLabel: "البلاستيك", image: familyIceCream },
    { id: "flin", label: "فلين", available: false, pricingLabel: "الفلين", image: familyIceCream },
  ],
  sizes: [
    { id: "half-liter", label: "1/2 لتر", maxBalls: 8, containerId: "classic-container", prices: [{ flavorFamily: "classic", price: 14 }, { flavorFamily: "special", price: 18 }, { flavorFamily: "mix", price: 16 }] },
    { id: "one-liter", label: "1 لتر", maxBalls: 12, containerId: "classic-container", prices: [{ flavorFamily: "classic", price: 28 }, { flavorFamily: "special", price: 35 }, { flavorFamily: "mix", price: 32 }] },
    { id: "half-liter-flin", label: "1/2 لتر", maxBalls: 8, containerId: "flin", prices: [{ flavorFamily: "classic", price: 16 }, { flavorFamily: "special", price: 20 }, { flavorFamily: "mix", price: 18 }] },
    { id: "one-liter-flin", label: "1 لتر", maxBalls: 12, containerId: "flin", prices: [{ flavorFamily: "classic", price: 31 }, { flavorFamily: "special", price: 38 }, { flavorFamily: "mix", price: 35 }] },
  ],
  selectionMode: "repeatable",
  flavorFamilies: ["classic", "special", "mix"],
  hasExtraBiscuitAddon: true,
};

// ─── Brad / Brad-boza ───────────────────────────────────────────────────────

const brad: IBuilderProduct = {
  id: "brad",
  slug: "brad",
  categoryId: "brad",
  kind: "builder",
  name: "براد",
  description: "شراب منعش بنكهات مختلفة",
  image: refrigerator,
  sortOrder: 1,
  available: true,
  pricingLabel: "البراد",
  containerOptions: [
    { id: "lemon", label: "ليمون", available: true },
    { id: "mango", label: "مانجا", available: true },
    { id: "mix", label: "مكس", available: true },
  ],
  sizes: [
    { id: "small", label: "صغير", maxBalls: 0, prices: [{ flavorFamily: "classic", price: 1 }] },
    { id: "medium", label: "وسط", maxBalls: 0, prices: [{ flavorFamily: "classic", price: 2 }] },
    { id: "large", label: "كبير", maxBalls: 0, prices: [{ flavorFamily: "classic", price: 3 }] },
  ],
};

const bradBoza: IBuilderProduct = {
  id: "brad-boza",
  slug: "brad-boza",
  categoryId: "brad-boza",
  kind: "builder",
  name: "براد مع بوظة",
  description: "براد منعش مع كرات بوظة لذيذة",
  image: iceCream,
  sortOrder: 1,
  available: true,
  pricingLabel: "البراد",
  includesIceCreamStep: true,
  iceCreamAddonPrices: [
    { flavorFamily: "classic", price: 3 },
    { flavorFamily: "special", price: 5 },
    { flavorFamily: "mix", price: 4 },
  ],
  containerOptions: [
    { id: "lemon", label: "ليمون", available: true },
    { id: "mango", label: "مانجا", available: true },
    { id: "mix", label: "مكس", available: true },
  ],
  sizes: [
    { id: "small", label: "صغير", maxBalls: 2, prices: [{ flavorFamily: "classic", price: 1 }] },
    { id: "medium", label: "وسط", maxBalls: 3, prices: [{ flavorFamily: "classic", price: 2 }] },
    { id: "large", label: "كبير", maxBalls: 4, prices: [{ flavorFamily: "classic", price: 3 }] },
  ],
  selectionMode: "toggle",
  flavorFamilies: ["classic", "special", "mix"],
};

// ─── Flat-list products ─────────────────────────────────────────────────────

const coldDrinksProduct: IFlatListProduct = {
  id: "cold-drinks",
  slug: "cold-drinks",
  categoryId: "cold-drinks",
  kind: "flat-list",
  name: "مشروبات باردة",
  description: "مشروبات منعشة وباردة في كل وقت",
  image: coldDrinks,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  hasNotes: true,
  items: [
    { id: "iced-coffee-caramel", label: "آيس كوفي كراميل", price: 8, description: "قهوة باردة مع كراميل منعش", image: caramelIce, available: true },
    { id: "iced-mocha", label: "آيس موكا", price: 8, description: "قهوة مع نكهة شوكولاتة باردة", image: nescafeIce, available: true },
    { id: "spanish-latte-caramel", label: "سبانش لاتيه كراميل", price: 10, description: "لاتيه إسباني برد مع كراميل", image: caramelIce, available: true },
    { id: "boba-shake", label: "بوبا شيك كوفي/فراولة", price: 12, description: "شيك لذيذ مع كرات الشاي", image: chocolateIce, available: true },
    { id: "small-water", label: "مياه صغيرة", price: 1, description: "مياه باردة ومنعشة", image: coconutIce, available: true },
  ],
};

const hotDrinksProduct: IFlatListProduct = {
  id: "hot-drinks",
  slug: "hot-drinks",
  categoryId: "hot-drinks",
  kind: "flat-list",
  name: "مشروبات ساخنة",
  description: "قهوة وشاي ساخن للأوقات الدافئة",
  image: hotDrinks,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  hasNotes: true,
  items: [
    { id: "arabic-coffee", label: "قهوة عربية", price: 5, description: "قهوة عربية تقليدية دافئة", image: caramelIce, available: true },
    { id: "hot-nescafe", label: "نسكافيه حار", price: 6, description: "مشروب نسكافيه ساخن لذيذ", image: nescafeIce, available: true },
    { id: "tea", label: "شاي", price: 4, description: "شاي ساخن مريح وشهي", image: lemonIce, available: true },
    { id: "hot-chocolate", label: "هوت شوكولاتة", price: 8, description: "شوكولاتة ساخنة غنية وسميكة", image: chocolateIce, available: true },
  ],
};

const juicesProduct: IFlatListProduct = {
  id: "juices",
  slug: "juices",
  categoryId: "juices",
  kind: "flat-list",
  name: "عصائر طبيعية",
  description: "عصائر طازة وطبيعية من أفضل الثمار",
  image: naturalJuices,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  hasNotes: true,
  items: [
    { id: "strawberry", label: "فراولة", price: 5, description: "عصير فراولة طازة وطبيعية", image: strawberryIce, available: true },
    { id: "blue-lemonade", label: "بلوليمونادا", price: 6, description: "عصير بلو ليمون منعش", image: lemonIce, available: true },
    { id: "mango", label: "مانجا", price: 7, description: "عصير مانجا حلو وعطري", image: mangoIce, available: true },
  ],
};

const cornProduct: IFlatListProduct = {
  id: "corn",
  slug: "corn",
  categoryId: "corn",
  kind: "flat-list",
  name: "ذرة",
  description: "ذرة شهية بنكهات مختلفة ولذيذة",
  image: corn,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  hasNotes: true,
  items: [
    { id: "plain", label: "ذرة سادة", price: 5, description: "ذرة طازة وشهية بدون إضافات", image: corn, available: true },
    { id: "cheese", label: "ذرة بالجبنة", price: 7, description: "ذرة مع نكهة جبن لذيذة", image: corn, available: true },
    { id: "chocolate", label: "ذرة بالشوكولاتة", price: 8, description: "ذرة حلوة مع شوكولاتة", image: chocolateIce, available: true },
  ],
};

const milkshakeProduct: IFlatListProduct = {
  id: "milkshake",
  slug: "milkshake",
  categoryId: "milkshake",
  kind: "flat-list",
  name: "ميلك شيك",
  description: "شيك لذيذ بنكهات كلاسيكية وخاصة",
  image: milkshake,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  addons: [
    { id: "extra-caramel", label: "صوص كراميل إضافي", price: 3, available: true },
    { id: "extra-nutella", label: "صوص نوتيلا إضافي", price: 4, available: true },
    { id: "extra-nuts", label: "بندق مبشور", price: 4, available: true },
    { id: "extra-oreo", label: "قطع أوريو", price: 3, available: true },
    { id: "extra-lotus", label: "بسكوت لوتس", price: 4, available: true },
    { id: "whipped-cream", label: "كريمة مخفوقة", price: 2, available: true },
  ],
  items: [
    { id: "chocolate", label: "كلاسيك شوكولاته", price: 8, description: "شيك شوكولاتة ناعم ولذيذ", image: chocolateIce, available: true },
    { id: "vanilla", label: "كلاسيك فانيلا", price: 8, description: "شيك فانيلا كلاسيكي", image: vanillaaIce, available: true },
    { id: "strawberry", label: "كلاسيك فراولة", price: 8, description: "شيك فراولة طازة", image: strawberryIce, available: false },
    { id: "caramel", label: "كلاسيك كاراميل", price: 8, description: "شيك كاراميل حلو وشهي", image: caramelIce, available: true },
    { id: "nescafe", label: "كلاسيك نسكافيه", price: 8, description: "شيك نسكافيه دافئ", image: nescafeIce, available: true },
    { id: "bazooka", label: "كلاسيك باروكا", price: 8, description: "شيك بطعم باروكا لذيذ", image: caramelIce, available: false },
    { id: "nutella", label: "سبيشال نوتيلا", price: 10, description: "شيك نوتيلا غنية", image: nutellaIce, available: true },
    { id: "lotus", label: "سبيشال لوتس", price: 10, description: "شيك بطعم لوتس فريد", image: lotusIce, available: true },
    { id: "kinder", label: "سبيشال كندر", price: 10, description: "شيك كندر بوينو", image: kinderBuenoIce, available: true },
    { id: "oreo", label: "سبيشال أوريو", price: 10, description: "شيك أوريو مع قطع البسكوت", image: oreoIce, available: false },
    { id: "kitkat", label: "سبيشال كت كات", price: 10, description: "شيك كت كات المقرمش", image: kitKatIce, available: true },
    { id: "fitness", label: "سبيشال فيتنس", price: 10, description: "شيك فيتنس صحي", image: nescafeIce, available: true },
    { id: "oat", label: "سبيشال شوفان", price: 10, description: "شيك شوفان غني وصحي", image: nutellaIce, available: true },
    { id: "cerelac", label: "سيرلاك (أطعم خاصة)", price: 8, description: "شيك سيرلاك تقليدي", image: marioIce, available: true },
    { id: "einstein", label: "اينشتاين (أطعم خاصة)", price: 9, description: "شيك اينشتاين حلو", image: marioIce, available: true },
    { id: "pistachio", label: "بيستاشيو (أطعم خاصة)", price: 13, description: "شيك بيستاشيو فاخر", image: pistachioIce, available: true },
  ],
};

const kunafaProduct: IFlatListProduct = {
  id: "kunafa",
  slug: "kunafa",
  categoryId: "kunafa",
  kind: "flat-list",
  name: "كنافة آيس كريم",
  description: "كنافة مقرمشة مع بوظة شهية",
  image: iceCreamKunafa,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "arabian", label: "كنافة عربية", price: 8, description: "كنافة مقرمشة مع بوظة عربية", image: arabianIce, available: true },
    { id: "lotus", label: "كنافة لوتس", price: 8, description: "كنافة لذيذة مع طعم لوتس", image: lotusIce, available: true },
    { id: "nutella", label: "كنافة نوتيلا", price: 8, description: "كنافة شهية مع نوتيلا", image: nutellaIce, available: true },
    { id: "blueberry", label: "كنافة بلوبيري", price: 8, description: "كنافة مع نكهة التوت الأزرق", image: blueberryIce, available: false },
    { id: "dondurma-pistachio", label: "كنافة دوندورما بيستاشيو", price: 12, description: "كنافة فاخرة مع دوندورما بيستاشيو", image: pistachioIce, available: true, isPremiumMixFlavor: true },
    { id: "energy", label: "كنافة طاقة (كل خميس)", price: 12, description: "كنافة خاصة متوفرة يوم الخميس", image: energyIce, available: false },
  ],
  mixes: [
    {
      id: "mix",
      label: "مكس (اختر طعمين)",
      pick: 2,
      basePrice: 10,
      flavorPrice: 5,
      premiumFlavorPrice: 8,
      itemIds: ["arabian", "lotus", "nutella", "blueberry", "dondurma-pistachio", "energy"],
    },
  ],
};

const loqaimatProduct: IFlatListProduct = {
  id: "loqaimat",
  slug: "loqaimat",
  categoryId: "loqaimat",
  kind: "flat-list",
  name: "لقيمات",
  description: "لقيمات طرية مع نكهات شهية",
  image: luqaimat,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "arabian", label: "لقيمة عربية", price: 8, description: "لقيمة طرية مع طعم عربي", image: arabianIce, available: true },
    { id: "lotus", label: "لقيمة لوتس", price: 8, description: "لقيمة شهية مع نكهة لوتس", image: lotusIce, available: true },
    { id: "nutella", label: "لقيمة نوتيلا", price: 8, description: "لقيمة لذيذة مع نوتيلا", image: nutellaIce, available: true },
    { id: "blueberry", label: "لقيمة بلوبيري", price: 8, description: "لقيمة مع طعم التوت الأزرق", image: blueberryIce, available: false },
    { id: "dondurma-pistachio", label: "لقيمة دوندورما بيستاشيو", price: 12, description: "لقيمة فاخرة مع دوندورما بيستاشيو", image: pistachioIce, available: true, isPremiumMixFlavor: true },
    { id: "energy", label: "لقيمة طاقة (كل خميس)", price: 12, description: "لقيمة خاصة متوفرة يوم الخميس", image: energyIce, available: false },
  ],
  mixes: [
    {
      id: "mix",
      label: "مكس (اختر طعمين)",
      pick: 2,
      basePrice: 10,
      flavorPrice: 5,
      premiumFlavorPrice: 8,
      itemIds: ["arabian", "lotus", "nutella", "blueberry", "dondurma-pistachio", "energy"],
    },
    {
      id: "super-mix",
      label: "سوبر مكس (اختر ثلاثة أطعمة)",
      pick: 3,
      basePrice: 15,
      flavorPrice: 5,
      premiumFlavorPrice: 8,
      itemIds: ["arabian", "lotus", "nutella", "blueberry", "dondurma-pistachio", "energy"],
    },
  ],
};

function dessertMixes(
  base2: number, flavor2: number, premium2: number,
  base3: number, flavor3: number, premium3: number,
  options: string[],
) {
  return [
    { id: "mix", label: "مكس", pick: 2, basePrice: base2, flavorPrice: flavor2, premiumFlavorPrice: premium2, itemIds: options },
    { id: "super-mix", label: "سوبر مكس", pick: 3, basePrice: base3, flavorPrice: flavor3, premiumFlavorPrice: premium3, itemIds: options },
  ];
}

const NUTELLA_LOTUS_PISTACHIO = ["nutella", "lotus", "pistachio"];

const pancakeProduct: IFlatListProduct = {
  id: "pancake",
  slug: "pancake",
  categoryId: "pancake",
  kind: "flat-list",
  name: "بان كيك",
  description: "بان كيك ناعم مع نكهات لذيذة",
  image: pancake,
  sortOrder: 1,
  available: true,
  inStoreOnly: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "nutella", label: "نوتيلا", price: 11, description: "بان كيك ناعم مع نوتيلا غنية", image: nutellaIce, available: true },
    { id: "lotus", label: "لوتس", price: 13, description: "بان كيك لذيذ مع طعم لوتس", image: lotusIce, available: true },
    { id: "pistachio", label: "بيستاشيو", price: 17, description: "بان كيك فاخر مع بيستاشيو", image: pistachioIce, available: true, isPremiumMixFlavor: true },
  ],
  mixes: dessertMixes(14, 7, 11, 18, 6, 10, NUTELLA_LOTUS_PISTACHIO),
};

const waffleProduct: IFlatListProduct = {
  id: "waffle",
  slug: "waffle",
  categoryId: "waffle",
  kind: "flat-list",
  name: "وافل",
  description: "وافل مقرمشة مع نكهات خاصة",
  image: waffle,
  sortOrder: 1,
  available: true,
  inStoreOnly: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "nutella", label: "نوتيلا", price: 10, description: "وافل مقرمش مع نوتيلا", image: nutellaIce, available: true },
    { id: "lotus", label: "لوتس", price: 12, description: "وافل فاخر مع طعم لوتس", image: lotusIce, available: true },
    { id: "pistachio", label: "بيستاشيو", price: 14, description: "وافل مميز مع بيستاشيو فاخر", image: pistachioIce, available: true, isPremiumMixFlavor: true },
  ],
  mixes: dessertMixes(14, 7, 11, 15, 5, 9, NUTELLA_LOTUS_PISTACHIO),
};

const crepeProduct: IFlatListProduct = {
  id: "crepe",
  slug: "crepe",
  categoryId: "crepe",
  kind: "flat-list",
  name: "كريب",
  description: "كريب رقيق مع حشوات شهية",
  image: crepe,
  sortOrder: 1,
  available: true,
  inStoreOnly: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "nutella", label: "نوتيلا", price: 9, description: "كريب رقيق وناعم مع نوتيلا", image: nutellaIce, available: true },
    { id: "lotus", label: "لوتس", price: 11, description: "كريب لذيذ مع طعم لوتس شهي", image: lotusIce, available: true },
    { id: "pistachio", label: "بيستاشيو", price: 13, description: "كريب فاخر مع بيستاشيو متميز", image: pistachioIce, available: true, isPremiumMixFlavor: true },
  ],
  mixes: dessertMixes(12, 6, 10, 15, 5, 9, NUTELLA_LOTUS_PISTACHIO),
};

const pizzaProduct: IFlatListProduct = {
  id: "pizza",
  slug: "pizza",
  categoryId: "pizza",
  kind: "flat-list",
  name: "بيتزا جلاسيه",
  description: "بيتزا حلوة مع نكهات لذيذة",
  image: glassyPizza,
  sortOrder: 1,
  available: true,
  inStoreOnly: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "nutella", label: "نوتيلا", price: 12, description: "بيتزا حلوة مع نوتيلا لذيذة", image: nutellaIce, available: true },
    { id: "lotus", label: "لوتس", price: 14, description: "بيتزا شهية مع طعم لوتس فريد", image: lotusIce, available: true },
    { id: "pistachio", label: "بيستاشيو", price: 16, description: "بيتزا فاخرة مع بيستاشيو متميز", image: pistachioIce, available: true, isPremiumMixFlavor: true },
  ],
  mixes: dessertMixes(16, 8, 12, 18, 6, 10, NUTELLA_LOTUS_PISTACHIO),
};

const moltenProduct: IFlatListProduct = {
  id: "molten",
  slug: "molten",
  categoryId: "molten",
  kind: "flat-list",
  name: "مولتن كيك",
  description: "كيك دافئ بقلب سائل وشهي",
  image: moltenCake,
  sortOrder: 1,
  available: true,
  inStoreOnly: true,
  hasFavorites: true,
  hasNotes: true,
  items: [
    { id: "nutella", label: "نوتيلا", price: 8, description: "كيك دافئ بقلب نوتيلا وبوظة", image: nutellaIce, available: true },
    { id: "lotus", label: "لوتس", price: 12, description: "كيك دافئ بقلب لوتس وبوظة", image: lotusIce, available: true },
    { id: "pistachio", label: "بستاشيو", price: 12, description: "كيك دافئ بقلب بيستاشيو وبوظة", image: pistachioIce, available: true },
  ],
};

const brownieProduct: IFlatListProduct = {
  id: "brownie",
  slug: "brownie",
  categoryId: "desserts",
  kind: "flat-list",
  name: "براونيز",
  description: "براونيز شوكولاتة غنية وطرية",
  image: browniesCake,
  sortOrder: 1,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  items: [
    { id: "plain", label: "براونيز عادي", price: 8, description: "براونيز شوكولاتة غنية وطرية", image: chocolateIce, available: true },
    { id: "nutella", label: "براونيز نوتيلا", price: 10, description: "براونيز مع نوتيلا لذيذة", image: nutellaIce, available: true },
    { id: "lotus", label: "براونيز لوتس", price: 10, description: "براونيز بطعم لوتس فريد", image: lotusIce, available: true },
  ],
};

const cookiesProduct: IFlatListProduct = {
  id: "cookies",
  slug: "cookies",
  categoryId: "desserts",
  kind: "flat-list",
  name: "كوكيز",
  description: "كوكيز طرية مع نكهات متنوعة",
  image: mochi,
  sortOrder: 2,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  items: [
    { id: "nutella", label: "كوكيز نوتيلا", price: 8, description: "كوكيز طري مع نوتيلا شهية", image: nutellaIce, available: true },
    { id: "lotus", label: "كوكيز لوتس", price: 10, description: "كوكيز لذيذ مع طعم لوتس", image: lotusIce, available: true },
    { id: "pistachio", label: "كوكيز بيستاشيو", price: 12, description: "كوكيز فاخر مع بيستاشيو", image: pistachioIce, available: true },
    { id: "mix", label: "كوكيز مكس", price: 10, description: "كوكيز مع نكهات متعددة", image: chocolateIce, available: true },
  ],
};

const cheesecakeProduct: IFlatListProduct = {
  id: "cheesecake",
  slug: "cheesecake",
  categoryId: "desserts",
  kind: "flat-list",
  name: "تشيز كيك",
  description: "تشيز كيك كريمي لذيذ مع نكهات خاصة",
  image: sanSebastian,
  sortOrder: 3,
  available: true,
  hasFavorites: true,
  hasImageZoom: true,
  items: [
    { id: "strawberry", label: "تشيز كيك فراولة", price: 12, description: "تشيز كيك كريمي مع فراولة", image: strawberryIce, available: true },
    { id: "lotus", label: "تشيز كيك لوتس", price: 14, description: "تشيز كيك لذيذ مع طعم لوتس", image: lotusIce, available: true },
    { id: "pistachio", label: "تشيز كيك بيستاشيو", price: 16, description: "تشيز كيك فاخر مع بيستاشيو", image: pistachioIce, available: true },
    { id: "mix", label: "تشيز كيك مكس", price: 14, description: "تشيز كيك مع نكهات متعددة", image: chocolateIce, available: true },
  ],
};

export const FAKE_PRODUCTS: IProduct[] = [
  cup,
  family,
  brad,
  bradBoza,
  coldDrinksProduct,
  hotDrinksProduct,
  juicesProduct,
  cornProduct,
  milkshakeProduct,
  kunafaProduct,
  loqaimatProduct,
  pancakeProduct,
  waffleProduct,
  crepeProduct,
  pizzaProduct,
  moltenProduct,
  brownieProduct,
  cookiesProduct,
  cheesecakeProduct,
];

/**
 * Fake additions (إضافات) catalog — the per-unit extras (with prices) a
 * customer can attach to a cart line via the "تخصيص الإضافات" flow. Served by
 * `GET /menu/addons` on the real backend; used here as initial/fallback data.
 * A product may still ship its own `addons` catalog to override this list.
 */
export const FAKE_ADDONS: IAddonOption[] = [
  { id: "extra-biscuit", label: "بسكوت مخروط", price: 3, available: true, type: "counter", maxQty: 15 },
  { id: "extra-caramel", label: "صوص كراميل", price: 3, available: true },
  { id: "extra-nutella", label: "صوص نوتيلا", price: 4, available: true },
  { id: "extra-nuts", label: "بندق مبشور", price: 4, available: true },
  { id: "extra-oreo", label: "قطع أوريو", price: 3, available: true },
  { id: "extra-lotus", label: "بسكوت لوتس", price: 4, available: true },
  { id: "whipped-cream", label: "كريمة مخفوقة", price: 2, available: true },
];

export function findFakeProductById(id: string): IProduct | undefined {
  return FAKE_PRODUCTS.find((p) => p.id === id);
}

export function findFakeProductBySlug(slug: string): IProduct | undefined {
  return FAKE_PRODUCTS.find((p) => p.slug === slug);
}

export function findFakeCategoryById(id: string): IMenuCategory | undefined {
  return FAKE_MENU_CATEGORIES.find((c) => c.id === id);
}
