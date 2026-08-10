import { i1, i2, i3, i4, imdDetails } from "@/assets/images";
import type { IEvent } from "@/types/events.types";

export const EVENTS_PER_PAGE = 8;

/** Full fake events catalog when `GET /events` / `GET /events/{id}` fails. */
export const FAKE_EVENTS: IEvent[] = [
  {
    id: 1,
    title: "مشارك معرض الصناعات الغذائية الفلسطينية",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i1,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 2,
    title: "أجواء العيد مع جلاسيه غير",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i2,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 3,
    title: "افتتاح فرع جديد فرع الأمن العام",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i3,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 4,
    title: "تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i4,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 5,
    title: "مشارك معرض الصناعات الغذائية الفلسطينية",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i1,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 6,
    title: "أجواء العيد مع جلاسيه غير",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i2,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 7,
    title: "افتتاح فرع جديد فرع الأمن العام",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i3,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 8,
    title: "تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i4,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 9,
    title: "افتتاح فرع جديد فرع الأمن العام",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i3,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
  {
    id: 10,
    title: "تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد",
    date: "11/06/2020",
    description:
      "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
    listImage: i4,
    images: [imdDetails, imdDetails, imdDetails, imdDetails],
  },
];

export function paginateEvents(
  events: IEvent[],
  page = 1,
  perPage = EVENTS_PER_PAGE,
) {
  const safePerPage = Math.max(1, perPage);
  const total = events.length;
  const totalPages = Math.max(1, Math.ceil(total / safePerPage));
  const safePage = Math.min(Math.max(1, page), totalPages);
  const start = (safePage - 1) * safePerPage;

  return {
    items: events.slice(start, start + safePerPage),
    total,
    page: safePage,
    perPage: safePerPage,
    totalPages,
  };
}

export function findFakeEventById(id: number): IEvent | undefined {
  return FAKE_EVENTS.find((e) => e.id === id);
}
