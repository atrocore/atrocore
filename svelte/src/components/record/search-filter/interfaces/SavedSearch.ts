export default interface SavedSearch {
  id: string;
  entityType: string;
  isPublic: boolean;
  name: string;
  userId: string;
  data: object
}