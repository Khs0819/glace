import type { IContactResponse } from "@/types/contact.types";

/** Fake success response when `POST /contact` fails / is unavailable. */
export const FAKE_CONTACT_SUBMIT_RESPONSE: IContactResponse = {
  success: true,
  message: "تم إرسال رسالتك بنجاح",
};
