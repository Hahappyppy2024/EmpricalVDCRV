from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
from models.post import PostRepository, CommentRepository
from decorators import login_required

post_bp = Blueprint('post', __name__)




@post_bp.route('/courses/<int:course_id>/posts', methods=['POST'])
@post_bp.route('/api/courses/<int:course_id>/posts', methods=['POST'])
@login_required
def create_post(course_id):
    data = request.get_json(silent=True) if request.is_json else request.form
    post_id = PostRepository.create(course_id, session['user_id'], data.get('title'), data.get('body'))

    if request.path.startswith('/api'):
        return jsonify({'post_id': post_id}), 201
    return redirect(url_for('post.get_post', course_id=course_id, post_id=post_id))


@post_bp.route('/courses/<int:course_id>/posts', methods=['GET'])
@post_bp.route('/api/courses/<int:course_id>/posts', methods=['GET'])
@login_required
def list_posts(course_id):
    keyword = request.args.get('keyword')
    if keyword:
        posts = PostRepository.search(course_id, keyword)
    else:
        posts = PostRepository.list_by_course(course_id)

    if request.path.startswith('/api'):
        return jsonify([dict(p) for p in posts])
    return render_template('posts/list.html', posts=posts, course_id=course_id)




@post_bp.route('/courses/<int:course_id>/posts/new', methods=['GET'])
@login_required
def new_post(course_id):
    return render_template('posts/form.html', course_id=course_id, post=None)


# 同时处理 HTML 表单提交和 API JSON 请求
@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>', methods=['GET'])
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['GET'])
@login_required
def get_post(course_id, post_id):
    post = PostRepository.get(post_id)
    comments = CommentRepository.list_by_post(post_id)
    if request.path.startswith('/api'):
        return jsonify({'post': dict(post), 'comments': [dict(c) for c in comments]})
    return render_template('posts/detail.html', post=post, comments=comments, course_id=course_id)


@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/edit', methods=['GET'])
@login_required
def edit_post(course_id, post_id):
    post = PostRepository.get(post_id)
    return render_template('posts/form.html', course_id=course_id, post=post)


# HTML 更新
@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>', methods=['POST'])
@login_required
def update_post_html(course_id, post_id):
    data = request.form
    PostRepository.update(post_id, data.get('title'), data.get('body'))
    return redirect(url_for('post.get_post', course_id=course_id, post_id=post_id))


# API 更新
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['PUT'])
@login_required
def update_post_api(course_id, post_id):
    data = request.get_json()
    PostRepository.update(post_id, data.get('title'), data.get('body'))
    return jsonify({'message': 'Updated'})


# 删除
@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/delete', methods=['POST'])
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>', methods=['DELETE'])
@login_required
def delete_post(course_id, post_id):
    PostRepository.delete(post_id)
    if request.is_json:
        return jsonify({'message': 'Deleted'})
    return redirect(url_for('post.list_posts', course_id=course_id))


# --- Comments ---

@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['POST'])
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['POST'])
@login_required
def create_comment(course_id, post_id):
    data = request.get_json() if request.is_json else request.form
    CommentRepository.create(post_id, course_id, session['user_id'], data.get('body'))

    if request.is_json:
        return jsonify({'message': 'Comment added'}), 201
    return redirect(url_for('post.get_post', course_id=course_id, post_id=post_id))


@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments', methods=['GET'])
@login_required
def list_comments_api(course_id, post_id):
    comments = CommentRepository.list_by_post(post_id)
    return jsonify([dict(c) for c in comments])


# HTML 更新评论 (简化处理，通常复杂应用需要单独页面)
@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['POST'])
@login_required
def update_comment_html(course_id, post_id, comment_id):
    data = request.form
    CommentRepository.update(comment_id, data.get('body'))
    return redirect(url_for('post.get_post', course_id=course_id, post_id=post_id))


# API 更新评论
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['PUT'])
@login_required
def update_comment_api(course_id, post_id, comment_id):
    data = request.get_json()
    CommentRepository.update(comment_id, data.get('body'))
    return jsonify({'message': 'Updated'})


# 删除评论
@post_bp.route('/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>/delete', methods=['POST'])
@post_bp.route('/api/courses/<int:course_id>/posts/<int:post_id>/comments/<int:comment_id>', methods=['DELETE'])
@login_required
def delete_comment(course_id, post_id, comment_id):
    CommentRepository.delete(comment_id)
    if request.is_json:
        return jsonify({'message': 'Deleted'})
    return redirect(url_for('post.get_post', course_id=course_id, post_id=post_id))


# Search
@post_bp.route('/api/courses/<int:course_id>/search/posts', methods=['GET'])
@login_required
def search_posts_api(course_id):
    keyword = request.args.get('keyword')
    posts = PostRepository.search(course_id, keyword) if keyword else []
