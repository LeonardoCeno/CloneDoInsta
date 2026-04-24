import { storeToRefs } from 'pinia'
import { useFeedStore, POST_CAPTION_MAX_LENGTH, normalizePost } from '@/stores/feed'

export { POST_CAPTION_MAX_LENGTH, normalizePost }

export function useFeed() {
  const feedStore = useFeedStore()
  const {
    feedPosts,
    feedCursor,
    feedHasNext,
    feedLoaded,
    feedLoading,
    savedPosts,
    savedHasNext,
    savedLoaded,
    savedLoading,
  } = storeToRefs(feedStore)

  return {
    feedPosts,
    feedCursor,
    feedHasNext,
    feedLoaded,
    feedLoading,
    savedPosts,
    savedHasNext,
    savedLoaded,
    savedLoading,
    fetchFeed: feedStore.fetchFeed,
    loadMoreFeed: feedStore.loadMoreFeed,
    createPost: feedStore.createPost,
    deletePost: feedStore.deletePost,
    toggleLike: feedStore.toggleLike,
    toggleSave: feedStore.toggleSave,
    fetchSaved: feedStore.fetchSaved,
    addComment: feedStore.addComment,
    applyPostPatch: feedStore.applyPostPatch,
  }
}
